<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use PhpParser\Node;

/**
 * The experimental graph is deliberately independent of production symbol kinds.
 */
final class DependencyGraph
{
    /**
     * @var array<string, Occurrence>
     */
    public array $nodes = [];

    /**
     * @var array<string, Dependency>
     */
    public array $edges = [];

    /**
     * @var array<string, string>
     */
    public array $diagnostics = [];

    /**
     * Complete syntax inventory, independent of transfer-rule coverage.
     */
    public ?\App\Analyzer\ExperimentAnalyzer\Structure\Inventory $inventory = null;

    /**
     * @var array<string, \App\Analyzer\ExperimentAnalyzer\Resolution\Issue>
     */
    public array $issues = [];

    /**
     * @var array<string, true>
     */
    public array $testedOrigins = [];

    /**
     * @var array<string, bool>
     */
    public array $scalars = [];

    /**
     * @var array<string, null|bool|int|list<string>|string>
     */
    public array $provenance = [];

    /**
     * @var array<string, array{int, string}>
     */
    private array $spans = [];

    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(
        public readonly string $target,
        public readonly string $file,
        private readonly string $source,
    ) {}

    /**
     * Records a source span with a role, keeping nested expressions distinct.
     */
    public function record(Node $node, string $kind, ?string $variable = null): string
    {
        $location = $this->location($node, $kind, $variable);
        $this->nodes[$location->id] ??= $location;

        return $location->id;
    }

    /**
     * Describes a source span independently of the solver graph.
     */
    public function location(Node $node, string $kind, ?string $variable = null): Occurrence
    {
        $position = $node->getStartFilePos();
        $span = $position.':'.$node->getEndFilePos();
        if (!isset($this->spans[$span])) {
            $prefix = substr($this->source, 0, $position);
            $newline = strrpos($prefix, "\n");
            $column = $position - ($newline === false ? -1 : $newline);
            $this->spans[$span] = [$column, trim(substr($this->source, $position, $node->getEndFilePos() - $position + 1))];
        }
        [$column, $text] = $this->spans[$span];
        $id = ($variable ?? $kind).'@'.$node->getStartLine().':'.$column.':'.$kind.':'.$node->getEndFilePos();

        return new Occurrence(
            $id,
            $kind,
            $variable,
            $node->getStartLine(),
            $column,
            $node->getEndLine(),
            $text,
        );
    }

    /**
     * Fingerprints the exact source parsed, including in-memory test sources.
     */
    public function fingerprint(): string
    {
        return hash('sha256', $this->source);
    }

    /**
     * Adds a dependency once, including its branch condition.
     */
    public function connect(string $from, string $to, string $kind = 'data', ?string $branch = null): void
    {
        $key = implode("\0", [$from, $to, $kind, $branch ?? '']);
        $this->edges[$key] = new Dependency($from, $to, $kind, $branch);
    }

    /**
     * Records a source-located analysis boundary once.
     */
    public function diagnose(Node $node, string $message): void
    {
        $message = 'Line '.$node->getStartLine().': '.$message;
        $this->diagnostics[$message] = $message;
    }

    /**
     * Commits a speculative transfer after proving that it cannot repeat.
     */
    public function adopt(self $trial): void
    {
        $this->nodes = $trial->nodes;
        $this->edges = $trial->edges;
        $this->issues = $trial->issues;
        $this->scalars = $trial->scalars;
        $this->testedOrigins = $trial->testedOrigins;
        $this->diagnostics = $trial->diagnostics;
    }

    /**
     * @return list<string>
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function select(int $line, ?string $variable, ?int $column = null): array
    {
        $roots = [];
        foreach ($this->nodes as $node) {
            if ($this->selectable($node) && $node->line === $line && ($variable === null || $node->variable === '$'.ltrim($variable, '$'))
                && ($column === null || $node->column === $column)
            ) {
                $roots[] = $node->id;
            }
        }
        if ($roots === [] && $this->inventory !== null && !str_contains($this->target, '::$')) {
            foreach ($this->inventory->sites as $site) {
                $node = $site->source;
                if ($node->line === $line && ($variable === null || $node->variable === '$'.ltrim($variable, '$')) && ($column === null || $node->column === $column)) {
                    $this->nodes[$node->id] = $node;
                    $this->issues[$node->id] = new \App\Analyzer\ExperimentAnalyzer\Resolution\Issue('NOT_ANALYZED', $node->id, 'inventory/v1', 'This source occurrence was retained but not evaluated (unreachable, deferred or unsupported region).', $node);
                    $roots[] = $node->id;
                }
            }
        }
        if ($roots === []) {
            throw new InspectionException(sprintf('No %soccurrence at line %d%s in %s.', $variable === null ? '' : $variable.' ', $line, $column === null ? '' : ', column '.$column, $this->target));
        }

        return $roots;
    }

    /**
     * Excludes synthetic state and keeps property addresses separate from local names.
     */
    public function selectable(Occurrence $node): bool
    {
        if (str_contains($this->target, '::$')) {
            return in_array($node->kind, ['property-declaration', 'property-access'], true);
        }

        return !in_array($node->kind, ['unbound', 'receiver', 'unknown-write', 'unknown-continuation', 'loop-input'], true);
    }

    /**
     * Starts an independent callable analysis over the same source text.
     */
    public function emptyCopy(): self
    {
        return new self($this->target, $this->file, $this->source);
    }
}
