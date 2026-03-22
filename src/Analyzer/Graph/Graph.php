<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\UnknownNodeId;

use function App\array_flatten;

/**
 * Represents a dependency graph of PHP code elements.
 *
 * The graph stores nodes (representing PHP code elements like classes, methods, etc.)
 * and edges (representing relationships between those elements). It uses an adjacency
 * list structure for efficient edge lookups and automatically creates bidirectional
 * edges with inverted edge kinds.
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
     * Adds a single node to the graph.
     *
     * If a node with the same ID already exists and is not Unknown, an exception is thrown.
     * Unknown nodes can be replaced by concrete node types.
     *
     * @param Node $node The node to add to the graph
     *
     * @throws \InvalidArgumentException If a non-Unknown node with the same ID already exists
     */
    public function addNode(Node $node): void
    {
        $nodeKey = $node->id()->toString();
        if (isset($this->nodes[$nodeKey]) && $this->nodes[$nodeKey]->kind() !== NodeKind::Unknown) {
            throw new \InvalidArgumentException('Node already exists.');
        }
        $this->nodes[$nodeKey] = $node;
        if (!isset($this->adjacency[$nodeKey])) {
            $this->adjacency[$nodeKey] = [];
        }
    }

    /**
     * Adds multiple nodes to the graph.
     *
     * @param Node[] $nodes Array of nodes to add
     */
    public function addNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    /**
     * Adds a single edge to the graph.
     *
     * Automatically creates Unknown nodes for any referenced nodes that don't exist yet.
     * Also creates a bidirectional edge in the opposite direction with an inverted edge kind.
     *
     * @param Edge $edge The edge to add to the graph
     */
    public function addEdge(Edge $edge): void
    {
        if (!isset($this->nodes[$edge->from()->toString()])) {
            $unknownId = $edge->from() instanceof UnknownNodeId
                ? $edge->from()
                : new UnknownNodeId($edge->from()->toString());
            $this->nodes[$edge->from()->toString()] = new UnknownNode(
                id: $unknownId,
            );
        }
        if (!isset($this->nodes[$edge->to()->toString()])) {
            $unknownId = $edge->to() instanceof UnknownNodeId
                ? $edge->to()
                : new UnknownNodeId($edge->to()->toString());
            $this->nodes[$edge->to()->toString()] = new UnknownNode(
                id: $unknownId,
            );
        }

        $this->adjacency[$edge->from()->toString()][] = $edge;
        $this->adjacency[$edge->to()->toString()][] = $edge->invert();
    }

    /**
     * Adds multiple edges to the graph.
     *
     * @param Edge[] $edges Array of edges to add
     */
    public function addEdges(array $edges): void
    {
        foreach ($edges as $edge) {
            $this->addEdge($edge);
        }
    }

    /**
     * Retrieves a node by its ID.
     *
     * @param NodeId<Node> $id The identifier of the node to retrieve
     *
     * @return null|Node The node if found, null otherwise
     */
    public function node(NodeId $id): ?Node
    {
        return $this->nodes[$id->toString()] ?? null;
    }

    /**
     * Retrieves all nodes in the graph.
     *
     * @return Node[] Array of all nodes
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * Retrieves an edge between two nodes.
     *
     * @param NodeId<Node> $from Source node identifier
     * @param NodeId<Node> $to   Target node identifier
     *
     * @return null|Edge The edge if found, null otherwise
     */
    public function edge(NodeId $from, NodeId $to): ?Edge
    {
        $edges = $this->adjacency[$from->toString()] ?? [];
        foreach ($edges as $edge) {
            if ($edge->to()->toString() === $to->toString()) {
                return $edge;
            }
        }

        return null;
    }

    /**
     * Retrieves all edges originating from a specific node.
     *
     * @param NodeId<Node> $from Source node identifier
     *
     * @return Edge[] Array of edges from the specified node
     */
    public function edges(NodeId $from): array
    {
        return $this->adjacency[$from->toString()] ?? [];
    }

    /**
     * Merges this graph with another graph, returning a new merged graph.
     *
     * Creates a new graph containing all nodes and edges from both graphs.
     * Existing Unknown nodes can be replaced by concrete types from the other graph.
     *
     * @param Graph $other The graph to merge with this one
     *
     * @return Graph A new graph containing nodes and edges from both graphs
     */
    public function merge(Graph $other): Graph
    {
        $merged = new Graph();
        $nodes = array_merge(
            $this->nodes(),
            $other->nodes()
        );
        $merged->addNodes($nodes);

        $edges = array_merge(
            array_flatten($this->adjacency),
            array_flatten($other->adjacency),
        );
        $merged->addEdges(
            array_filter($edges, function (Edge $edge) {
                return $edge->kind() !== EdgeKind::UsedBy && $edge->kind() !== EdgeKind::DeclaredIn;
            })
        );

        return $merged;
    }
}
