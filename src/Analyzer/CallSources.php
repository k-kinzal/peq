<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Declaration\PhpDoc\DocIndex;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;

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
     * @var array<string, array{method: string, traits: list<string>}>
     */
    private array $aliases = [];

    /**
     * Selects the PHP version used to read callable bodies.
     */
    public function __construct(private readonly ?int $version, private readonly ?PhaseCache $cache = null, public readonly DocIndex $docs = new DocIndex()) {}

    /**
     * @return list<ClassMethod|Function_>
     */
    public function file(string $path): array
    {
        return $this->files[$path] ??= $this->read($path);
    }

    /**
     * Finds the syntax of a graph declaration by its source location.
     */
    public function callable(Node $symbol): ?FunctionLike
    {
        $meta = $symbol->meta();
        if ($meta === null) {
            return null;
        }
        $declarations = $this->file($meta->path);
        $candidates = array_values(array_filter($declarations, static fn (ClassMethod|Function_ $node): bool => $node->getStartLine() === $meta->line));
        $alias = $this->aliases[strtolower($symbol->id()->toString())] ?? null;
        if ($alias !== null) {
            foreach ($candidates as $candidate) {
                $owner = $candidate->getAttribute('peqOwner');
                if ($candidate instanceof ClassMethod && strtolower($candidate->name->toString()) === $alias['method'] && is_string($owner) && in_array(strtolower($owner), $alias['traits'], true)) {
                    return $candidate;
                }
            }
        }
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
        $resolved = CachedSyntax::read($path, SourceParser::forVersion($this->version), $this->version, $this->cache)->statements;
        if ($resolved === null) {
            return [];
        }
        $this->docs->read($resolved);
        $classes = (new NodeFinder())->findInstanceOf($resolved, \PhpParser\Node\Stmt\ClassLike::class);
        foreach ($classes as $class) {
            foreach ($class->getMethods() as $method) {
                $method->setAttribute('peqOwner', $class->namespacedName?->toString());
            }
            foreach ($class->getTraitUses() as $use) {
                foreach ($use->adaptations as $adaptation) {
                    if ($adaptation instanceof \PhpParser\Node\Stmt\TraitUseAdaptation\Alias && $adaptation->newName !== null) {
                        $this->aliases[strtolower($class->namespacedName?->toString().'::'.$adaptation->newName->toString())] = [
                            'method' => strtolower($adaptation->method->toString()),
                            'traits' => array_values(array_map(static fn (\PhpParser\Node\Name $name): string => strtolower($name->toString()), $adaptation->trait === null ? $use->traits : [$adaptation->trait])),
                        ];
                    }
                }
            }
        }
        $found = (new NodeFinder())->find($resolved, static fn (object $node): bool => $node instanceof ClassMethod || $node instanceof Function_);

        return array_values(array_filter($found, static fn (object $node): bool => $node instanceof ClassMethod || $node instanceof Function_));
    }
}
