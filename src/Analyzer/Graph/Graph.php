<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

use App\Analyzer\Graph\Node\UnknownNode;

/**
 * Represents a dependency graph of PHP code elements.
 *
 * The graph stores nodes (representing PHP code elements like classes, methods, etc.)
 * and edges (representing relationships between those elements). It uses an adjacency
 * list structure for efficient edge lookups and automatically records every edge in
 * both directions, so that reading the graph towards a symbol costs the same as
 * reading it away from one.
 */
final class Graph
{
    /**
     * @var array<string, Node> Map of node ID strings to Node objects
     */
    private array $nodes = [];

    /**
     * @var array<string, list<Edge>> Adjacency list mapping source node IDs to a list of edges
     */
    private array $adjacency = [];

    /**
     * @var array<string, true> Hash set for O(1) edge duplicate detection, keyed by "from\0kind\0to"
     */
    private array $edgeSet = [];

    /**
     * Records a node in the graph.
     *
     * Recording is idempotent, because an identifier names one symbol however many
     * times analysis meets it. Which of several meetings the graph keeps is decided
     * by how much each one says about the symbol rather than by which arrived first:
     * a placeholder left behind by an edge gives way to the real node, and a bare
     * reference never displaces the declaration that was actually read. Meetings that
     * say the same amount are the same meeting, and the recorded one stays.
     *
     * @param Node $node The node to record
     */
    public function addNode(Node $node): void
    {
        $nodeKey = $node->id()->toString();
        $recorded = $this->nodes[$nodeKey] ?? null;
        if ($recorded !== null && !NodePrecedence::prefers($recorded, $node)) {
            return;
        }

        $this->nodes[$nodeKey] = $node;
        if (!isset($this->adjacency[$nodeKey])) {
            $this->adjacency[$nodeKey] = [];
        }
    }

    /**
     * Records several nodes in the graph.
     *
     * @param iterable<Node> $nodes The nodes to record
     */
    public function addNodes(iterable $nodes): void
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    /**
     * Records an edge in the graph together with its opposite reading.
     *
     * Endpoints that have not been seen yet are recorded as unresolved placeholders,
     * so an edge is never left dangling. Recording is idempotent per direction and
     * kind, and the inverse edge is derived from the edge itself, so no relation
     * kind is lost by making the reverse direction available.
     *
     * @example Recording a relation makes it readable from both of its ends
     *     $meta = new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1);
     *     $caller = new \App\Analyzer\Graph\Node\MethodNode(
     *         \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Invoice', 'total'), true, $meta);
     *     $called = new \App\Analyzer\Graph\Node\MethodNode(
     *         \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Money', 'add'), true, $meta);
     *     $graph = new \App\Analyzer\Graph\Graph();
     *     $graph->addEdge(new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $called, $meta));
     *     $graph->edge($called->id(), $caller->id())?->kind() // => \App\Analyzer\Graph\EdgeKind::UsedBy
     *
     * @param Edge $edge The edge to record
     */
    public function addEdge(Edge $edge): void
    {
        $fromKey = $edge->from()->toString();
        $toKey = $edge->to()->toString();

        if (!isset($this->nodes[$fromKey])) {
            $this->addNode(UnknownNode::standingInFor($edge->from()));
        }
        if (!isset($this->nodes[$toKey])) {
            $this->addNode(UnknownNode::standingInFor($edge->to()));
        }

        $edgeKey = $fromKey."\0".$edge->kind()->value."\0".$toKey;
        if (isset($this->edgeSet[$edgeKey])) {
            return;
        }

        $this->edgeSet[$edgeKey] = true;
        $this->adjacency[$fromKey][] = $edge;

        $inverse = $edge->invert();
        $inverseKey = $toKey."\0".$inverse->kind()->value."\0".$inverse->to()->toString();
        if (!isset($this->edgeSet[$inverseKey])) {
            $this->edgeSet[$inverseKey] = true;
            $this->adjacency[$toKey][] = $inverse;
        }
    }

    /**
     * Records several edges in the graph.
     *
     * @param iterable<Edge> $edges The edges to record
     */
    public function addEdges(iterable $edges): void
    {
        foreach ($edges as $edge) {
            $this->addEdge($edge);
        }
    }

    /**
     * Retrieves a node by its identifier.
     *
     * @param NodeId<Node> $id The identifier of the node to retrieve
     *
     * @return null|Node The node if recorded, null otherwise
     */
    public function node(NodeId $id): ?Node
    {
        return $this->nodes[$id->toString()] ?? null;
    }

    /**
     * Finds the node a written symbol name refers to.
     *
     * Nodes are keyed by the string form of their identifier, which is the form a
     * symbol is written in on the command line, so a name resolves in one lookup
     * rather than a scan of the graph. A name that matches nothing resolves to null
     * rather than to the closest thing found.
     *
     * @param string $name The fully qualified symbol name, as written
     *
     * @example A symbol that was recorded is found by the name it is written as
     *     $graph = new \App\Analyzer\Graph\Graph();
     *     $graph->addNode(new \App\Analyzer\Graph\Node\ClassNode(
     *         \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice'), true));
     *     $graph->nodeNamed('App\\Domain\\Invoice')?->kind() // => \App\Analyzer\Graph\NodeKind::Klass
     * @example A name the graph does not hold resolves to nothing
     *     (new \App\Analyzer\Graph\Graph())->nodeNamed('App\\Domain\\Invoice') // => null
     *
     * @return null|Node The node with that name, or null when the graph holds none
     */
    public function nodeNamed(string $name): ?Node
    {
        return $this->nodes[$name] ?? null;
    }

    /**
     * Retrieves every node recorded in the graph.
     *
     * @return list<Node> All recorded nodes
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * Retrieves an edge between two nodes, optionally restricted to one kind.
     *
     * @param NodeId<Node>  $from Source node identifier
     * @param NodeId<Node>  $to   Target node identifier
     * @param null|EdgeKind $kind Kind to restrict the search to; null matches any kind
     *
     * @return null|Edge The first matching edge, or null when there is none
     */
    public function edge(NodeId $from, NodeId $to, ?EdgeKind $kind = null): ?Edge
    {
        $edges = $this->adjacency[$from->toString()] ?? [];
        foreach ($edges as $edge) {
            if ($edge->to()->toString() === $to->toString()
                && ($kind === null || $edge->kind() === $kind)
            ) {
                return $edge;
            }
        }

        return null;
    }

    /**
     * Retrieves every edge starting at a node, in both directions of reading.
     *
     * @param NodeId<Node> $from Source node identifier
     *
     * @return list<Edge> The edges recorded for that node
     */
    public function edges(NodeId $from): array
    {
        return $this->adjacency[$from->toString()] ?? [];
    }

    /**
     * Combines this graph with another one into a new graph.
     *
     * Only authored edges are carried over: the opposite readings are derived
     * again by the new graph, so a merge cannot accumulate stale inverses.
     *
     * @param Graph $other The graph to combine with this one
     *
     * @return Graph A new graph holding the nodes and edges of both
     */
    public function merge(Graph $other): Graph
    {
        $merged = new Graph();
        $merged->addNodes($this->nodes());
        $merged->addNodes($other->nodes());
        $merged->addEdges($this->authoredEdges());
        $merged->addEdges($other->authoredEdges());

        return $merged;
    }

    /**
     * Returns every edge that source code actually writes, without derived inverses.
     *
     * @return list<Edge> The authored edges of this graph
     */
    public function authoredEdges(): array
    {
        $authored = [];
        foreach ($this->adjacency as $edges) {
            foreach ($edges as $edge) {
                if (!$edge instanceof InverseEdge) {
                    $authored[] = $edge;
                }
            }
        }

        return $authored;
    }
}
