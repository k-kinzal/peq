<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use App\Gql\GqlException;
use App\Gql\StatusCode;
use Override;

/**
 * An exact number with digits after the point.
 *
 * GQL keeps two kinds of number apart, as SQL does. An exact number is exactly what was
 * written: `1.5` is one and a half, and `0.1 + 0.2` is `0.3`. An approximate number is
 * the nearest one a binary fraction can hold, which is what `1.5e0`, `1.5f` and `1.5d`
 * ask for. A literal with a point and no exponent is exact, so it is not a float.
 *
 * The value is held as a whole number and a scale — `1.5` is 15 with a scale of 1 — so
 * that nothing is ever rounded to fit a binary fraction. The whole number is a PHP
 * integer, which bounds the digits a value may have at eighteen; a value that would
 * need more is out of range rather than silently approximated.
 */
final class DecimalDatum implements Datum
{
    /**
     * @param int $unscaled The digits, as one whole number
     * @param int $scale    How many of them come after the point
     */
    public function __construct(
        public readonly int $unscaled,
        public readonly int $scale,
    ) {
        assert($this->scale >= 0, 'An exact number has no fewer than no digits after its point');
    }

    /**
     * Reads an exact number as a query writes it.
     *
     * A number with no digits after the point, once any exponent is applied, is a whole
     * number: `15M` and `1.5e1M` are both fifteen, and both are integers.
     *
     * @param string $written The literal, with or without its exponent and its `M`
     *
     * @example A point makes a number exact rather than approximate
     *     \App\Gql\Datum\DecimalDatum::written('1.5')->toText() // => '1.5'
     * @example An exponent moves the point
     *     \App\Gql\Datum\DecimalDatum::written('1.5e-1M')->toText() // => '0.15'
     * @example A number with nothing after the point is a whole number
     *     \App\Gql\Datum\DecimalDatum::written('1.5e1M') instanceof \App\Gql\Datum\IntegerDatum // => true
     *
     * @return DecimalDatum|IntegerDatum The number
     *
     * @throws GqlException If it has more digits than an exact number can hold
     */
    public static function written(string $written): IntegerDatum|self
    {
        $plain = rtrim($written, 'mM');
        $exponent = 0;
        $marked = stripos($plain, 'e');
        if ($marked !== false) {
            $exponent = (int) substr($plain, $marked + 1);
            $plain = substr($plain, 0, $marked);
        }
        $point = strpos($plain, '.');
        $digits = str_replace('.', '', $plain);
        $scale = ($point === false ? 0 : strlen($plain) - $point - 1) - $exponent;
        if ($scale < 0) {
            $digits .= str_repeat('0', -$scale);
            $scale = 0;
        }

        return self::of(self::whole($digits), $scale);
    }

    /**
     * Returns an exact number, as an integer when it has nothing after its point.
     *
     * @param int $unscaled The digits, as one whole number
     * @param int $scale    How many of them come after the point
     *
     * @example A scale of nothing is an integer
     *     \App\Gql\Datum\DecimalDatum::of(15, 0) instanceof \App\Gql\Datum\IntegerDatum // => true
     *
     * @return DecimalDatum|IntegerDatum The number
     */
    public static function of(int $unscaled, int $scale): IntegerDatum|self
    {
        return $scale === 0 ? new IntegerDatum($unscaled) : new self($unscaled, $scale);
    }

    /**
     * Reads a run of digits as a whole number, refusing one too long to hold.
     *
     * @param string $digits The digits, with no sign
     *
     * @example Digits read as the number they spell
     *     \App\Gql\Datum\DecimalDatum::whole('0042') // => 42
     * @example One too long for an exact number is out of range
     *     \App\Gql\Datum\DecimalDatum::whole('99999999999999999999') // throws \App\Gql\GqlException: numeric value out of range
     *
     * @return int The number
     *
     * @throws GqlException If it is too long to hold
     */
    public static function whole(string $digits): int
    {
        $trimmed = ltrim($digits, '0');
        $limit = (string) PHP_INT_MAX;
        if (strlen($trimmed) > strlen($limit) || (strlen($trimmed) === strlen($limit) && strcmp($trimmed, $limit) > 0)) {
            throw GqlException::because(
                StatusCode::NumericValueOutOfRange,
                sprintf('%s has more digits than an exact number can hold', $digits),
            );
        }

        return (int) $trimmed;
    }

    /**
     * Reports that the value is an exact number with digits after its point.
     *
     * @example A decimal is one
     *     (new \App\Gql\Datum\DecimalDatum(15, 1))->kind() // => \App\Gql\Datum\DatumKind::Decimal
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Decimal;
    }

    /**
     * Writes the number with exactly as many digits after its point as its scale.
     *
     * @example A number is written as exactly what it is
     *     (new \App\Gql\Datum\DecimalDatum(-5, 2))->toText() // => '-0.05'
     */
    #[Override]
    public function toText(): string
    {
        $digits = str_pad(ltrim((string) $this->unscaled, '-'), $this->scale + 1, '0', STR_PAD_LEFT);
        $point = strlen($digits) - $this->scale;

        return ($this->unscaled < 0 ? '-' : '').substr($digits, 0, $point).'.'.substr($digits, $point);
    }

    /**
     * Returns the nearest approximate number, for arithmetic that is approximate anyway.
     *
     * @example A decimal read as a float is its nearest binary fraction
     *     (new \App\Gql\Datum\DecimalDatum(15, 1))->toFloat() // => 1.5
     *
     * @return float The number
     */
    public function toFloat(): float
    {
        return $this->unscaled / 10 ** $this->scale;
    }
}
