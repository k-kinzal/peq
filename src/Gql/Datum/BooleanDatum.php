<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A truth value that is decided.
 *
 * GQL's third truth value, unknown, is not here: it is null, and is represented by
 * the absence of a value. That is the standard's own arrangement and it is worth
 * keeping, because it makes "this filter kept only the rows where the predicate was
 * true" a single rule rather than two.
 */
final readonly class BooleanDatum implements Datum
{
    /**
     * @param bool $value Whether it is true
     */
    public function __construct(
        public bool $value,
    ) {}

    /**
     * Returns the decided truth value for a PHP boolean.
     *
     * @param bool $value Whether it is true
     *
     * @example A decided truth value is a value like any other
     *     \App\Gql\Datum\BooleanDatum::of(true)->value // => true
     *
     * @return self The truth value
     */
    public static function of(bool $value): self
    {
        return new self($value);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Boolean;
    }

    /**
     * Writes the truth value out the way GQL writes one.
     *
     * @example Truth is written the way a query writes it
     *     (new \App\Gql\Datum\BooleanDatum(true))->toText() // => 'TRUE'
     * @example And so is its opposite
     *     (new \App\Gql\Datum\BooleanDatum(false))->toText() // => 'FALSE'
     *
     * @return string The word GQL writes for it
     */
    #[Override]
    public function toText(): string
    {
        return $this->value ? 'TRUE' : 'FALSE';
    }
}
