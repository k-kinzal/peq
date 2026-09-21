<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A list of values, which GQL calls a LIST.
 *
 * Lists arrive three ways: written out in a query, collected by an aggregate, and
 * bound by a variable-length pattern to every edge along a path. The third is the one
 * that matters most here, because it is what lets a query ask how far away something
 * is without knowing in advance how far it will turn out to be.
 */
final readonly class ListDatum implements Datum
{
    /**
     * @param list<Datum> $items The values, in order
     */
    public function __construct(
        public array $items,
    ) {}

    /**
     * Returns a list of no values.
     *
     * @example A list can hold nothing, and still be a list
     *     \App\Gql\Datum\ListDatum::empty()->items // => []
     *
     * @return self The empty list
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::ListOf;
    }

    /**
     * Writes the list out the way a result shows it.
     *
     * @example A list is shown as its values, in the order it holds them
     *     $rows = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2)]);
     *     $rows->toText() // => '[1, 2]'
     *
     * @return string The list, written out
     */
    #[Override]
    public function toText(): string
    {
        $written = array_map(static fn (Datum $item): string => $item->toText(), $this->items);

        return '['.implode(', ', $written).']';
    }
}
