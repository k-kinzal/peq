<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Reads callable bodies once per source file, including methods imported from traits.
 */
final class CallSources
{
    /**
     * @var array<string, list<ClassMethod|Function_>>
     */
    private array $files = [];

    /**
     * Selects the PHP version used to read callable bodies.
     */
    public function __construct(private readonly ?int $version) {}

    /**
     * Finds the syntax of a graph declaration by its source location.
     */
    public function callable(Node $symbol): ?FunctionLike
    {
        $meta = $symbol->meta();
        if ($meta === null) {
            return null;
        }
        $declarations = $this->files[$meta->path] ??= $this->read($meta->path);
        $candidates = array_values(array_filter($declarations, static fn (ClassMethod|Function_ $node): bool => $node->getStartLine() === $meta->line));
        foreach ($candidates as $candidate) {
            if ($candidate instanceof ClassMethod && $candidate->getAttribute('peqOwner') === ClassHierarchy::owner($symbol) && str_ends_with(strtolower($symbol->id()->toString()), '::'.strtolower($candidate->name->toString()))) {
                return $candidate;
            }
        }
        foreach ($candidates as $candidate) {
            if (str_ends_with(strtolower($symbol->id()->toString()), '::'.strtolower($candidate->name->toString()))
                || ($candidate instanceof Function_ && $candidate->namespacedName?->toString() === $symbol->id()->toString())
            ) {
                return $candidate;
            }
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * @return list<ClassMethod|Function_>
     */
    public function read(string $path): array
    {
        $text = is_readable($path) ? file_get_contents($path) : false;
        if ($text === false) {
            return [];
        }
        $errors = new Collecting();
        $statements = SourceParser::forVersion($this->version)->parse($text, $errors);
        if ($statements === null || $errors->hasErrors()) {
            return [];
        }
        $traverser = new NodeTraverser(new NameResolver());
        $resolved = $traverser->traverse($statements);
        $classes = (new NodeFinder())->findInstanceOf($resolved, \PhpParser\Node\Stmt\ClassLike::class);
        foreach ($classes as $class) {
            foreach ($class->getMethods() as $method) {
                $method->setAttribute('peqOwner', $class->namespacedName?->toString());
            }
        }
        $found = (new NodeFinder())->find($resolved, static fn (object $node): bool => $node instanceof ClassMethod || $node instanceof Function_);

        return array_values(array_filter($found, static fn (object $node): bool => $node instanceof ClassMethod || $node instanceof Function_));
    }
}
