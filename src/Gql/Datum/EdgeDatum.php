<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A reference to an edge of the graph, which GQL calls an EDGE.
 *
 * An edge knows which nodes it joins and in which direction, because direction is
 * what an edge is for: a method that calls another is not the same fact as a method
 * that is called by it. The endpoints are carried as identities rather than as node
 * references so that an edge stays a small value, and so that a pattern that binds
 * only the edge does not drag two nodes along with it.
 */
final class EdgeDatum implements Datum
{
    /**
     * @param string               $id         What identifies the edge, unique in its graph
     * @param list<string>         $labels     The labels the edge carries
     * @param array<string, Datum> $properties The properties the edge carries, by name
     * @param string               $origin     What identifies the node the edge leaves
     * @param string               $target     What identifies the node the edge arrives at
     */
    public function __construct(
        public readonly string $id,
        public readonly array $labels,
        public readonly array $properties,
        public readonly string $origin,
        public readonly string $target,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Edge;
    }

    /**
     * Returns a property of the edge, or the absence of one.
     *
     * @param string $name The property name
     *
     * @example A property the edge carries reads as its value
     *     $edge = new \App\Gql\Datum\EdgeDatum('e1', ['methodCall'], ['line' => new \App\Gql\Datum\IntegerDatum(12)], 'a', 'b');
     *     $edge->property('line')->toText() // => '12'
     * @example A property it does not carry reads as absent
     *     $edge = new \App\Gql\Datum\EdgeDatum('e1', [], [], 'a', 'b');
     *     $edge->property('line')->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The property value, or the absence of one
     */
    public function property(string $name): Datum
    {
        return $this->properties[$name] ?? new NullDatum();
    }

    /**
     * Returns the most particular of the labels the edge carries.
     *
     * An edge carries what it is and the families it belongs to — `methodCall`,
     * `call`, `usage` — and a reader being shown one wants the first of those. The
     * families exist so that a pattern can select by them, not so that a drawing can
     * repeat them.
     *
     * @example The most particular label is the one a reader is shown
     *     (new \App\Gql\Datum\EdgeDatum('e', ['methodCall', 'call', 'usage'], [], 'a', 'b'))->label() // => 'methodCall'
     * @example An edge that carries no label has nothing to be shown as
     *     (new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'))->label() // => ''
     *
     * @return string The label, or an empty string when it carries none
     */
    public function label(): string
    {
        return $this->labels[0] ?? '';
    }

    /**
     * Writes the edge out the way a result shows it.
     *
     * @example An edge is shown as the relation it is, between the symbols it joins
     *     $edge = new \App\Gql\Datum\EdgeDatum('e1', ['methodCall'], [], 'App\\A::run', 'App\\B::go');
     *     $edge->toText() // => 'App\\A::run -[methodCall]-> App\\B::go'
     * @example An edge with no label says only that it is one
     *     (new \App\Gql\Datum\EdgeDatum('e1', [], [], 'a', 'b'))->toText() // => 'a -[]-> b'
     *
     * @return string The edge, written the way a pattern writes one
     */
    #[Override]
    public function toText(): string
    {
        return $this->origin.' -['.$this->label().']-> '.$this->target;
    }
}
