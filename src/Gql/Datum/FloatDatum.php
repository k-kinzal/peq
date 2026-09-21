<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * An approximate number, which GQL calls a FLOAT64.
 *
 * Every average a query computes is one of these, and so is every arithmetic
 * expression that met one, because GQL's coercion rule is that approximate spreads.
 */
final readonly class FloatDatum implements Datum
{
    /**
     * @param float $value The number
     */
    public function __construct(
        public float $value,
    ) {}

    /**
     * Returns the approximate number for a PHP float.
     *
     * @param float $value The number
     *
     * @example An approximate number stays approximate
     *     \App\Gql\Datum\FloatDatum::of(1.5)->value // => 1.5
     *
     * @return self The number
     */
    public static function of(float $value): self
    {
        return new self($value);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Float;
    }

    /**
     * Writes the number out the way a result shows it.
     *
     * A number that happens to have no fractional part keeps one, so that a column of
     * averages reads as a column of averages rather than as a mixture.
     *
     * The two results that are not numbers are named rather than converted. PHP warns
     * when an undefined result is turned into text, which is the right warning to get
     * in most code and the wrong one here: a query that computed one has an answer to
     * report, and reporting it should not disturb the run that asked.
     *
     * @example An approximate number is shown as one
     *     (new \App\Gql\Datum\FloatDatum(1.5))->toText() // => '1.5'
     * @example One that came out whole still says it is approximate
     *     (new \App\Gql\Datum\FloatDatum(2.0))->toText() // => '2.0'
     * @example A result that is not a number is named
     *     (new \App\Gql\Datum\FloatDatum(NAN))->toText() // => 'NAN'
     * @example One beyond every number is named, with its sign
     *     (new \App\Gql\Datum\FloatDatum(-INF))->toText() // => '-INF'
     *
     * @return string The number, written out
     */
    #[Override]
    public function toText(): string
    {
        if (is_nan($this->value)) {
            return 'NAN';
        }
        if (is_infinite($this->value)) {
            return $this->value > 0 ? 'INF' : '-INF';
        }

        $written = (string) $this->value;

        return str_contains($written, '.') || str_contains($written, 'E') ? $written : $written.'.0';
    }
}
