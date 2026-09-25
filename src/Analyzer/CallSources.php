<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Declaration\PhpDoc\DocBlock;
use App\Analyzer\Declaration\PhpDoc\DocIndex;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use Override;
use PhpParser\Node as Syntax;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitorAbstract;

/**
 * Reads callable bodies with at most one source file retained between lookups.
 */
final class CallSources extends NodeVisitorAbstract
{
    /**
     * @var list<ClassMethod|Function_>
     */
    private array $declarations = [];

    /**
     * Only the most recently used file stays parsed, including a missing file.
     */
    private ?string $path = null;

    /**
     * @var array<string, array{method: string, traits: list<string>}>
     */
    private array $aliases = [];

    /**
     * Declaration locations let PHPDoc lookups reuse indexed comments without syntax.
     *
     * @var array<string, array{string, int}>
     */
    private array $declared = [];

    /**
     * Selects the PHP version used to read callable bodies.
     */
    public function __construct(private readonly ?int $version, private readonly ?PhaseCache $cache = null, public readonly DocIndex $docs = new DocIndex()) {}

    /**
     * @return list<ClassMethod|Function_>
     */
    public function file(string $path): array
    {
        return $this->path === $path ? $this->declarations : $this->read($path);
    }

    /**
     * Reads indexed method documentation, locating imported trait bodies when needed.
     */
    public function documentation(Node $symbol): ?DocBlock
    {
        $meta = $symbol->meta();
        $key = DocIndex::key($symbol->id()->toString());
        if ($meta !== null && ($this->declared[$key] ?? null) === [$meta->path, $meta->line]) {
            return $this->docs->blocks[$key] ?? null;
        }
        $syntax = $this->callable($symbol);

        return $syntax === null ? null : DocIndex::block($syntax);
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
        $this->declarations = [];
        $this->path = $path;
        $resolved = CachedSyntax::read($path, SourceParser::forVersion($this->version), $this->version, $this->cache)->statements;
        if ($resolved === null) {
            return [];
        }
        $this->docs->read($resolved, $this);

        return $this->declarations;
    }

    /**
     * Collects callable owners and trait aliases during the PHPDoc namespace walk.
     */
    #[Override]
    public function enterNode(Syntax $node): null
    {
        if ($node instanceof Syntax\Stmt\ClassLike) {
            foreach ($node->getMethods() as $method) {
                $method->setAttribute('peqOwner', $node->namespacedName?->toString());
            }
            foreach ($node->getTraitUses() as $use) {
                foreach ($use->adaptations as $adaptation) {
                    if ($adaptation instanceof Syntax\Stmt\TraitUseAdaptation\Alias && $adaptation->newName !== null) {
                        $this->aliases[strtolower($node->namespacedName?->toString().'::'.$adaptation->newName->toString())] = [
                            'method' => strtolower($adaptation->method->toString()),
                            'traits' => array_values(array_map(static fn (Syntax\Name $name): string => strtolower($name->toString()), $adaptation->trait === null ? $use->traits : [$adaptation->trait])),
                        ];
                    }
                }
            }
        }
        if ($node instanceof ClassMethod || $node instanceof Function_) {
            $this->declarations[] = $node;
        }
        if ($node instanceof ClassMethod && $this->path !== null) {
            $owner = $node->getAttribute('peqOwner');
            if (is_string($owner)) {
                $this->declared[DocIndex::key($owner.'::'.$node->name->toString())] = [$this->path, $node->getStartLine()];
            }
        }

        return null;
    }
}
