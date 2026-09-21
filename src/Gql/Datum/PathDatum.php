<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A path through the graph, which GQL calls a PATH.
 *
 * A path is the whole of what a pattern matched: the nodes it passed through and the
 * edges it crossed, in the order it met them. It always begins and ends with a node,
 * and edges and nodes alternate — that is what makes it a path rather than a bag of
 * elements, and it is why `nodes()`, `edges()` and `path_length()` can be answered
 * without asking the graph anything.
 *
 * Binding one is how a query gets an answer it can act on: not "these two symbols are
 * connected" but "here is the chain of calls between them".
 */
final readonly class PathDatum implements Datum
{
    /**
     * @param list<Datum> $elements The nodes and edges met, in order, beginning and ending with a node
     */
    public function __construct(
        public array $elements,
    ) {
        assert($this->elements !== [], 'A path passes through at least one node');
        assert($this->elements[0] instanceof NodeDatum, 'A path begins at a node');
        assert($this->elements[count($this->elements) - 1] instanceof NodeDatum, 'A path ends at a node');
    }

    /**
     * Returns the path of a single node, which crosses no edge.
     *
     * A variable-length pattern whose lower bound is zero matches this, and so does
     * every path before its first edge is crossed.
     *
     * @param NodeDatum $node The node the path stands at
     *
     * @example A path that crosses nothing is still a path
     *     \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('App\\Invoice'))->length() // => 0
     *
     * @return self The path of that one node
     */
    public static function at(NodeDatum $node): self
    {
        return new self([$node]);
    }

    /**
     * Returns this path continued across an edge to a node.
     *
     * @param EdgeDatum $edge The edge crossed
     * @param NodeDatum $node The node arrived at
     *
     * @example Continuing a path makes it one edge longer
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'));
     *     $edge = new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b');
     *     $path->continuedBy($edge, new \App\Gql\Datum\NodeDatum('b'))->length() // => 1
     *
     * @return self The longer path
     */
    public function continuedBy(EdgeDatum $edge, NodeDatum $node): self
    {
        return new self([...$this->elements, $edge, $node]);
    }

    /**
     * Returns the nodes the path passes through, in order.
     *
     * @example A path of one edge passes through two nodes
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     count($path->nodes()) // => 2
     *
     * @return list<NodeDatum> The nodes
     */
    public function nodes(): array
    {
        $nodes = [];
        foreach ($this->elements as $element) {
            if ($element instanceof NodeDatum) {
                $nodes[] = $element;
            }
        }

        return $nodes;
    }

    /**
     * Returns the edges the path crosses, in order.
     *
     * @example A path that crosses nothing has no edges
     *     \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->edges() // => []
     *
     * @return list<EdgeDatum> The edges
     */
    public function edges(): array
    {
        $edges = [];
        foreach ($this->elements as $element) {
            if ($element instanceof EdgeDatum) {
                $edges[] = $element;
            }
        }

        return $edges;
    }

    /**
     * Returns the node the path ends at.
     *
     * @example A path that crosses nothing ends where it began
     *     \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->last()->id // => 'a'
     *
     * @return NodeDatum The last node
     */
    public function last(): NodeDatum
    {
        $last = $this->elements[count($this->elements) - 1];
        assert($last instanceof NodeDatum, 'A path ends at a node');

        return $last;
    }

    /**
     * Returns how many edges the path crosses.
     *
     * @example The length of a path is the number of edges it crosses, not of nodes
     *     \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->length() // => 0
     *
     * @return int The number of edges
     */
    public function length(): int
    {
        return intdiv(count($this->elements), 2);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Path;
    }

    /**
     * Writes the path out the way a result shows it.
     *
     * An edge crossed against its own direction is written with the arrow pointing
     * back, because a path that reached a caller from the method it calls went that
     * way, and drawing it the other way would say the opposite of what was found.
     *
     * @example A path reads as the chain it is
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     $path->toText() // => 'a -[calls]-> b'
     * @example An edge crossed the other way keeps pointing the way it points
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('b'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('a'));
     *     $path->toText() // => 'b <-[calls]- a'
     *
     * @return string The path, written as a chain
     */
    #[Override]
    public function toText(): string
    {
        $written = '';
        $previous = null;
        foreach ($this->elements as $element) {
            $written .= $element instanceof EdgeDatum
                ? self::arrow($element, $previous instanceof NodeDatum && $previous->id === $element->origin)
                : $element->toText();
            $previous = $element;
        }

        return $written;
    }

    /**
     * Writes one edge of a path as the arrow the path crossed it by.
     *
     * @param EdgeDatum $edge    The edge crossed
     * @param bool      $forward Whether it was crossed the way it points
     *
     * @example An edge crossed the way it points is drawn pointing that way
     *     \App\Gql\Datum\PathDatum::arrow(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), true) // => ' -[calls]-> '
     * @example One crossed against it is drawn pointing back
     *     \App\Gql\Datum\PathDatum::arrow(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), false) // => ' <-[calls]- '
     *
     * @return string The arrow, with the spaces that separate it from its ends
     */
    public static function arrow(EdgeDatum $edge, bool $forward): string
    {
        $labels = $edge->label();

        return $forward ? ' -['.$labels.']-> ' : ' <-['.$labels.']- ';
    }
}
