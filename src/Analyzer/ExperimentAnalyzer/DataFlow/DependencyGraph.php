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
        $position = $node->getStartFilePos();
        $prefix = substr($this->source, 0, $position);
        $newline = strrpos($prefix, "\n");
        $column = $position - ($newline === false ? -1 : $newline);
        $id = ($variable ?? $kind).'@'.$node->getStartLine().':'.$column.':'.$kind.':'.$node->getEndFilePos();
        $this->nodes[$id] ??= new Occurrence(
            $id,
            $kind,
            $variable,
            $node->getStartLine(),
            $column,
            $node->getEndLine(),
            trim(substr($this->source, $position, $node->getEndFilePos() - $position + 1)),
        );

        return $id;
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
     * @return list<string>
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function select(int $line, ?string $variable, ?int $column = null): array
    {
        $roots = [];
        foreach ($this->nodes as $node) {
            if ($node->kind !== 'unbound' && $node->kind !== 'receiver' && $node->line === $line && ($variable === null || $node->variable === '$'.ltrim($variable, '$'))
                && ($column === null || $node->column === $column)
            ) {
                $roots[] = $node->id;
            }
        }
        if ($roots === []) {
            throw new InspectionException(sprintf('No %soccurrence at line %d%s in %s.', $variable === null ? '' : $variable.' ', $line, $column === null ? '' : ', column '.$column, $this->target));
        }

        return $roots;
    }
}
