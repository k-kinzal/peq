<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * A graph written down in a canonical form, so that two graphs can be compared.
 *
 * A graph is built by recording symbols and relations in whatever order analysis
 * happened to meet them, which means two graphs that describe the same codebase are
 * equal without being identical: the same nodes arrive in a different order, and the
 * adjacency lists that hold them are keyed differently as a result. Comparing them
 * therefore has to go through a form that keeps what a graph says and drops the order
 * it was said in — one sorted line per symbol and per relation.
 *
 * Only forward relations are written down. The opposite readings are derived from
 * them by the graph itself, so two graphs with the same forward relations have the
 * same derived ones, and writing both down would only report every difference twice.
 *
 * This is what makes a second analyzer checkable against the first: they are the same
 * engine exactly when they produce the same snapshot of the same sources.
 */
final class GraphSnapshot
{
    /**
     * @param list<string> $nodes One line per symbol, sorted
     * @param list<string> $edges One line per forward relation, sorted
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $edges,
    ) {}

    /**
     * Writes a graph down in its canonical form.
     *
     * @param Graph $graph The graph to write down
     *
     * @example Two graphs built in opposite orders are written down the same way
     *     $meta = new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1);
     *     $one = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('A'), true, $meta);
     *     $two = new \App\Analyzer\Graph\Node\ClassNode(\App\Analyzer\Graph\NodeId\ClassNodeId::of('B'), true, $meta);
     *     $first = new \App\Analyzer\Graph\Graph();
     *     $first->addNodes([$one, $two]);
     *     $second = new \App\Analyzer\Graph\Graph();
     *     $second->addNodes([$two, $one]);
     *     \App\Analyzer\Graph\GraphSnapshot::of($first)->fingerprint() === \App\Analyzer\Graph\GraphSnapshot::of($second)->fingerprint() // => true
     *
     * @return self The canonical form of that graph
     */
    public static function of(Graph $graph): self
    {
        $nodes = [];
        foreach ($graph->nodes() as $node) {
            $nodes[] = sprintf(
                '%s %s resolved=%s at %s%s',
                $node->kind()->value,
                $node->id()->toString(),
                $node->resolved() ? 'yes' : 'no',
                self::placeOf($node->meta()),
                $node->declaration() === null ? '' : ' declaration='.serialize($node->declaration()),
            );
        }
        sort($nodes);

        $edges = [];
        foreach ($graph->forwardEdges() as $edge) {
            $edges[] = sprintf(
                '%s %s -> %s at %s%s',
                $edge->kind()->value,
                $edge->from()->toString(),
                $edge->to()->toString(),
                self::placeOf($edge->meta()),
                EdgeIdentity::evidence($edge) === [] ? '' : ' evidence='.serialize(EdgeIdentity::evidence($edge)),
            );
        }
        sort($edges);

        return new self($nodes, $edges);
    }

    /**
     * Writes down where a symbol or a relation stands in the sources.
     *
     * @param null|FileMeta $meta The place, or null when the symbol has none
     *
     * @example A symbol that was never declared in the analyzed sources stands nowhere
     *     \App\Analyzer\Graph\GraphSnapshot::placeOf(null) // => 'nowhere'
     *
     * @return string The place, written so that sorting it is stable
     */
    public static function placeOf(?FileMeta $meta): string
    {
        return $meta === null ? 'nowhere' : sprintf('%s:%d:%d', $meta->path, $meta->line, $meta->column).($meta->offset === null ? '' : '@'.$meta->offset);
    }

    /**
     * Returns one string that stands for everything this snapshot says.
     *
     * Comparing fingerprints answers whether two graphs are the same; comparing the
     * snapshots themselves answers how they differ. The cheap question is asked far
     * more often than the expensive one, so it has its own answer.
     *
     * @example The fingerprint of an empty graph is the fingerprint of nothing
     *     \App\Analyzer\Graph\GraphSnapshot::of(new \App\Analyzer\Graph\Graph())->fingerprint() === hash('sha256', '') // => true
     *
     * @return string The fingerprint of this snapshot
     */
    public function fingerprint(): string
    {
        return hash('sha256', $this->toString());
    }

    /**
     * Returns everything this snapshot says, as text.
     *
     * @example An empty graph says nothing
     *     \App\Analyzer\Graph\GraphSnapshot::of(new \App\Analyzer\Graph\Graph())->toString() // => ''
     *
     * @return string One line per symbol and per relation
     */
    public function toString(): string
    {
        return implode("\n", [...$this->nodes, ...$this->edges]);
    }

    /**
     * Reports how this snapshot differs from the one that was expected.
     *
     * @param self $expected The snapshot this one is meant to equal
     *
     * @example A snapshot does not differ from itself
     *     $snapshot = \App\Analyzer\Graph\GraphSnapshot::of(new \App\Analyzer\Graph\Graph());
     *     $snapshot->differenceFrom($snapshot)->isEmpty() // => true
     *
     * @return GraphDifference What one holds and the other does not
     */
    public function differenceFrom(self $expected): GraphDifference
    {
        return new GraphDifference(
            missingNodes: array_values(array_diff($expected->nodes, $this->nodes)),
            unexpectedNodes: array_values(array_diff($this->nodes, $expected->nodes)),
            missingEdges: array_values(array_diff($expected->edges, $this->edges)),
            unexpectedEdges: array_values(array_diff($this->edges, $expected->edges)),
        );
    }
}
