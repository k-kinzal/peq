<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;

/**
 * One active analysed file, answered from while a walker or lookup holds it.
 *
 * A file is read for three different questions — what it declares, what its method
 * bodies reach out to, and what its anonymous classes are called — and parsing it
 * once for each question is wasteful. These lookups share a tree for the active
 * file; SourceIndex retains declaration locations instead of keeping every file
 * alive for the duration of the project analysis.
 *
 * @visibility namespace
 */
final class ParsedSource
{
    /**
     * The finder used to locate declarations, built once per file.
     */
    private ?NodeFinder $finder = null;

    /**
     * The declaration found under each name looked up so far.
     *
     * @var array<string, null|ClassLike>
     */
    private array $foundDeclarations = [];

    /**
     * The methods each declaration looked up so far writes, keyed by name.
     *
     * @var array<string, array<string, ClassMethod>>
     */
    private array $foundMethods = [];

    /**
     * @param string               $path       Absolute path of the file
     * @param list<Stmt>           $statements Its statements, with every written name resolved
     * @param AnonymousClassNaming $anonymous  The names of its anonymous classes
     */
    public function __construct(
        public readonly string $path,
        public readonly array $statements,
        public readonly AnonymousClassNaming $anonymous,
    ) {}

    /**
     * Returns the body of one method as it is written in this file.
     *
     * Bodies are located by the name of the declaring class-like, not by walking in
     * from it, because a method may be reached in analysis through a trait or an
     * anonymous class whose declaration is nowhere near the class it belongs to. A
     * method the file does not actually write — one a class takes on from a trait
     * declared elsewhere — has no body here, and that is reported as none.
     *
     * @param string $className  Fully qualified name of the declaring class-like
     * @param string $methodName Name of the method
     *
     * @return null|list<Stmt> The method body, or null when this file does not write one
     */
    public function methodBody(string $className, string $methodName): ?array
    {
        $method = $this->methodsOf($className)[$methodName] ?? null;

        return $method !== null && $method->stmts !== null ? array_values($method->stmts) : null;
    }

    /**
     * Returns the methods written under a declaration, keyed by the name they answer to.
     *
     * Reading them all at once is what keeps a class of many methods from costing a
     * search of its whole body for each of them. A name written more than once — in
     * the class and again in an anonymous class inside it — answers to the one
     * written first, which is the one a search from the top would have found.
     *
     * @param string $className Fully qualified name of the declaring class-like
     *
     * @return array<string, ClassMethod> The methods that declaration writes
     */
    public function methodsOf(string $className): array
    {
        if (array_key_exists($className, $this->foundMethods)) {
            return $this->foundMethods[$className];
        }

        $declaration = $this->declarationOf($className);
        if (!$declaration instanceof Class_ && !$declaration instanceof Trait_ && !$declaration instanceof Enum_) {
            return $this->foundMethods[$className] = [];
        }

        $finder = $this->finder ??= new NodeFinder();
        $methods = [];
        foreach ($finder->find($declaration->stmts, static fn (PhpParserNode $node): bool => $node instanceof ClassMethod) as $method) {
            assert($method instanceof ClassMethod);
            $methods[$method->name->toString()] ??= $method;
        }

        return $this->foundMethods[$className] = $methods;
    }

    /**
     * Returns the class-like this file declares under the given name.
     *
     * @param string $className Fully qualified name of the declaration
     *
     * @return null|ClassLike The declaration, or null when this file does not write one
     */
    public function declarationOf(string $className): ?ClassLike
    {
        if (array_key_exists($className, $this->foundDeclarations)) {
            return $this->foundDeclarations[$className];
        }

        $finder = $this->finder ??= new NodeFinder();
        $declaration = $finder->findFirst($this->statements, static function (PhpParserNode $node) use ($className): bool {
            return ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_)
                && $node->namespacedName !== null
                && $node->namespacedName->toString() === $className;
        });

        return $this->foundDeclarations[$className] = $declaration instanceof ClassLike ? $declaration : null;
    }
}
