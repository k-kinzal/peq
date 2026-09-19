<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use PhpParser\Node\Name;

/**
 * Where in the sources the walk currently stands.
 *
 * Every relation the graph records is written somewhere, and what that somewhere is
 * decides both which symbol the relation starts at and what the names written there
 * mean: `self` means one class here and another one line further down, and an
 * unqualified call means one function inside a namespace and another outside it.
 * Carrying that context explicitly — rather than reconstructing it from the syntax
 * tree at each name — is what lets the walk stay a single pass.
 *
 * A scope is never changed: entering a class or a function makes a new one, so the
 * walk can descend and return without having to undo anything.
 *
 * @visibility namespace
 */
final class AnalysisScope
{
    /**
     * The symbol relations written here start at, worked out once.
     */
    private ?Node $source = null;

    /**
     * @param SourceIndex  $index        What the analysed files declare
     * @param string       $file         Absolute path of the file being walked
     * @param null|string  $className    Fully qualified name of the class-like being walked, or null outside one
     * @param null|string  $parentName   Fully qualified name of that class-like's parent, or null when it has none analysis can find
     * @param list<string> $traitChain   Fully qualified names of the traits whose statements are being walked, outermost first
     * @param null|string  $methodName   Name of the method being walked, or null outside one
     * @param null|string  $functionName Fully qualified name of the function being walked, or null outside one
     */
    public function __construct(
        public readonly SourceIndex $index,
        public readonly string $file,
        public readonly ?string $className = null,
        public readonly ?string $parentName = null,
        public readonly array $traitChain = [],
        public readonly ?string $methodName = null,
        public readonly ?string $functionName = null,
    ) {}

    /**
     * Starts a walk at the top of a file.
     *
     * @param SourceIndex $index What the analysed files declare
     * @param string      $file  Absolute path of the file
     *
     * @return self A scope standing outside any declaration of that file
     */
    public static function inFile(SourceIndex $index, string $file): self
    {
        return new self($index, $file);
    }

    /**
     * Enters a class-like declaration.
     *
     * @param string      $className  Fully qualified name of the declaration
     * @param null|string $parentName Fully qualified name of its parent, or null when it has none analysis can find
     *
     * @return self A scope standing inside that declaration
     */
    public function enteringClass(string $className, ?string $parentName): self
    {
        return new self($this->index, $this->file, $className, $parentName);
    }

    /**
     * Enters the statements a class-like takes on from a trait.
     *
     * The class stays what it was: a trait's statements are read as if written in the
     * class that uses them, which is what makes a method taken on from a trait a
     * method of that class and not of the trait.
     *
     * @param string $traitName Fully qualified name of the trait being read
     *
     * @return self A scope standing in the same class, inside that trait's statements
     */
    public function enteringTrait(string $traitName): self
    {
        return new self($this->index, $this->file, $this->className, $this->parentName, [...$this->traitChain, $traitName]);
    }

    /**
     * Reports whether a trait is already being read further up this walk.
     *
     * A trait that uses a trait that uses it back is a cycle PHP rejects, and
     * analysis stops at the point it would start going round.
     *
     * @param string $traitName Fully qualified name of the trait
     *
     * @return bool True when the walk is already inside that trait
     */
    public function isReading(string $traitName): bool
    {
        foreach ($this->traitChain as $reading) {
            if (strtolower($reading) === strtolower($traitName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the trait whose statements are being read as the class's own.
     *
     * @return null|string The innermost trait being read, or null outside any
     */
    public function readingTrait(): ?string
    {
        $last = array_key_last($this->traitChain);

        return $last === null ? null : $this->traitChain[$last];
    }

    /**
     * Enters the body of a method of the class this scope stands in.
     *
     * @param string $methodName Name of the method
     *
     * @return self A scope standing inside that method
     */
    public function enteringMethod(string $methodName): self
    {
        return new self($this->index, $this->file, $this->className, $this->parentName, $this->traitChain, $methodName);
    }

    /**
     * Enters the body of a function.
     *
     * A function written inside a class body does not leave it: the reference engine
     * keeps the class it was written in, and reads the function's body as something
     * written in that class rather than as a place of its own. A relation written
     * there therefore belongs to no symbol an impact analysis can report, and none is
     * recorded — which is exactly what happens in a class body outside any method.
     *
     * @param string $functionName Fully qualified name of the function
     *
     * @return self A scope standing inside that function
     */
    public function enteringFunction(string $functionName): self
    {
        return new self($this->index, $this->file, $this->className, $this->parentName, $this->traitChain, $this->methodName, $functionName);
    }

    /**
     * Resolves a written class name to the class it names here.
     *
     * The keywords PHP resolves against the current declaration are the only names
     * whose meaning depends on where they are written; every other name has already
     * been resolved to its full form when the file was parsed. A `parent` written in
     * a class whose parent analysis cannot find stays `parent`, and is therefore
     * dropped as a name no codebase declares.
     *
     * @param Name $name The written name
     *
     * @return string The name it refers to here
     */
    public function resolveName(Name $name): string
    {
        $written = $name->toString();
        if ($this->className === null) {
            return $written;
        }

        $lowered = strtolower($written);
        if ($lowered === 'self' || $lowered === 'static') {
            return $this->className;
        }

        return $lowered === 'parent' && $this->parentName !== null ? $this->parentName : $written;
    }

    /**
     * Resolves a written function name the way PHP looks a function up.
     *
     * An unqualified call inside a namespace means the function of that namespace
     * when one exists and the global function otherwise, and which of the two it is
     * cannot be read off the name. A name that resolves to neither is left as
     * written, which is the same answer the global fallback would give.
     *
     * @param Name $name The written name
     *
     * @return string The name of the function it calls
     */
    public function resolveFunctionName(Name $name): string
    {
        $namespaced = $name->getAttribute('namespacedName');
        if ($namespaced instanceof Name && $this->index->declaresFunction($namespaced->toString())) {
            return $namespaced->toString();
        }

        return $name->toString();
    }

    /**
     * Returns the symbol the relations written here start at.
     *
     * Inside a method the symbol is that method, inside a function it is that
     * function, and inside a class but outside any of its methods it is the class
     * itself. At the top level of a file there is no declaration to attribute
     * anything to, so the file stands in as an unresolved symbol.
     *
     * @return Node The symbol relations written here belong to
     */
    public function sourceNode(): Node
    {
        if ($this->source !== null) {
            return $this->source;
        }
        if ($this->className !== null) {
            return $this->source = $this->methodName !== null
                ? new MethodNode(MethodNodeId::of($this->className, $this->methodName), true, null)
                : new ClassNode(ClassNodeId::of($this->className), true, null);
        }

        return $this->source = $this->functionName !== null
            ? new FunctionNode(FunctionNodeId::of($this->functionName), true, null)
            : new UnknownNode(new UnknownNodeId($this->file));
    }
}
