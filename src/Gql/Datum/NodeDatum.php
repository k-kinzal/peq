<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A reference to a node of the graph, which GQL calls a NODE.
 *
 * The reference carries the node's labels and properties rather than pointing back at
 * the graph it came from. That is what keeps values independent of the structure they
 * were read out of: an expression asking for `p.name` never has to know which graph
 * `p` was bound in, and a row that outlives the statement that produced it still
 * answers the same questions.
 *
 * The identity is the node's own — the symbol's fully qualified name — so two
 * references to the same symbol are the same node however they were reached.
 */
final class NodeDatum implements Datum
{
    /**
     * @param string               $id         What identifies the node, unique in its graph
     * @param list<string>         $labels     The labels the node carries
     * @param array<string, Datum> $properties The properties the node carries, by name
     */
    public function __construct(
        public readonly string $id,
        public readonly array $labels = [],
        public readonly array $properties = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Node;
    }

    /**
     * Returns a property of the node, or the absence of one.
     *
     * A property a node does not carry reads as absent rather than as an error, which
     * is what lets one query ask about a property only some of the matched nodes
     * have — the visibility of a method, on a pattern that also matches classes.
     *
     * @param string $name The property name
     *
     * @example A property the node carries reads as its value
     *     $node = new \App\Gql\Datum\NodeDatum('App\\Invoice', ['Class'], ['name' => new \App\Gql\Datum\StringDatum('Invoice')]);
     *     $node->property('name')->toText() // => 'Invoice'
     * @example A property it does not carry reads as absent
     *     (new \App\Gql\Datum\NodeDatum('App\\Invoice'))->property('visibility')->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The property value, or the absence of one
     */
    public function property(string $name): Datum
    {
        return $this->properties[$name] ?? new NullDatum();
    }

    /**
     * Writes the node out the way a result shows it.
     *
     * @example A node is shown as what identifies it
     *     (new \App\Gql\Datum\NodeDatum('App\\Domain\\Invoice', ['Class']))->toText() // => 'App\\Domain\\Invoice'
     *
     * @return string What identifies the node
     */
    #[Override]
    public function toText(): string
    {
        return $this->id;
    }
}
