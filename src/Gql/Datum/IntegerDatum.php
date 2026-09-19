<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A whole number, which GQL calls an INT64.
 *
 * It is kept apart from the approximate kind rather than folded into one numeric
 * value because GQL's coercion rules depend on the difference: an expression that
 * mixes the two produces an approximate result, and one that does not stays exact.
 * A line number that came out as 12.0 would be a small lie about what was read.
 */
final class IntegerDatum implements Datum
{
    /**
     * @param int $value The number
     */
    public function __construct(
        public readonly int $value,
    ) {}

    /**
     * Returns the whole number for a PHP integer.
     *
     * @param int $value The number
     *
     * @example A whole number stays whole
     *     \App\Gql\Datum\IntegerDatum::of(42)->value // => 42
     *
     * @return self The number
     */
    public static function of(int $value): self
    {
        return new self($value);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Integer;
    }

    /**
     * Writes the number out the way a result shows it.
     *
     * @example A whole number is shown without a fractional part
     *     (new \App\Gql\Datum\IntegerDatum(42))->toText() // => '42'
     *
     * @return string The number, written out
     */
    #[Override]
    public function toText(): string
    {
        return (string) $this->value;
    }
}
