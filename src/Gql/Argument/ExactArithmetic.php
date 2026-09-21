<?php

declare(strict_types=1);

namespace App\Gql\Argument;

use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Arithmetic on exact numbers, which stays exact.
 *
 * ISO/IEC 39075 makes the result of an operator on two exact numbers an exact number,
 * and leaves its precision and scale to the implementation. peq's choices, which the
 * conformance register states item by item:
 *
 * - addition and subtraction keep the larger of the two scales (ID065);
 * - multiplication adds the two scales (ID066);
 * - division of two whole numbers is a whole number, and division with a decimal on
 *   either side has six digits after the point, or more when either side has more,
 *   or fewer — but never fewer than either side has — when six would not fit (ID067);
 *   either way the digits that do not fit are cut off, not rounded;
 * - a result with more than eighteen digits is out of range, and says so (22003),
 *   rather than turning quietly into an approximate number.
 *
 * @visibility App\Gql
 */
final class ExactArithmetic
{
    /**
     * The fewest digits after the point a division with a decimal in it keeps.
     */
    public const int DIVISION_SCALE = 6;

    /**
     * Adds two exact numbers.
     *
     * @param DecimalDatum|IntegerDatum $left  The number on the left
     * @param DecimalDatum|IntegerDatum $right The number on the right
     *
     * @example Exact numbers add exactly
     *     \App\Gql\Argument\ExactArithmetic::add(new \App\Gql\Datum\DecimalDatum(1, 1), new \App\Gql\Datum\DecimalDatum(2, 1))->toText() // => '0.3'
     *
     * @return DecimalDatum|IntegerDatum The sum
     *
     * @throws GqlException If the sum has more digits than an exact number can hold
     */
    public static function add(DecimalDatum|IntegerDatum $left, DecimalDatum|IntegerDatum $right): DecimalDatum|IntegerDatum
    {
        $scale = max(self::scaleOf($left), self::scaleOf($right));

        return DecimalDatum::of(self::checked(self::scaled($left, $scale) + self::scaled($right, $scale)), $scale);
    }

    /**
     * Subtracts one exact number from another.
     *
     * @param DecimalDatum|IntegerDatum $left  The number subtracted from
     * @param DecimalDatum|IntegerDatum $right The number subtracted
     *
     * @example Exact numbers subtract exactly
     *     \App\Gql\Argument\ExactArithmetic::subtract(new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\DecimalDatum(25, 2))->toText() // => '0.75'
     *
     * @return DecimalDatum|IntegerDatum The difference
     *
     * @throws GqlException If the difference has more digits than an exact number can hold
     */
    public static function subtract(DecimalDatum|IntegerDatum $left, DecimalDatum|IntegerDatum $right): DecimalDatum|IntegerDatum
    {
        $scale = max(self::scaleOf($left), self::scaleOf($right));

        return DecimalDatum::of(self::checked(self::scaled($left, $scale) - self::scaled($right, $scale)), $scale);
    }

    /**
     * Multiplies two exact numbers.
     *
     * @param DecimalDatum|IntegerDatum $left  The number on the left
     * @param DecimalDatum|IntegerDatum $right The number on the right
     *
     * @example The scales of a product add up
     *     \App\Gql\Argument\ExactArithmetic::multiply(new \App\Gql\Datum\DecimalDatum(15, 1), new \App\Gql\Datum\DecimalDatum(2, 1))->toText() // => '0.30'
     * @example Whole numbers too large to multiply are out of range
     *     \App\Gql\Argument\ExactArithmetic::multiply(new \App\Gql\Datum\IntegerDatum(PHP_INT_MAX), new \App\Gql\Datum\IntegerDatum(2)) // throws \App\Gql\GqlException: numeric value out of range
     *
     * @return DecimalDatum|IntegerDatum The product
     *
     * @throws GqlException If the product has more digits than an exact number can hold
     */
    public static function multiply(DecimalDatum|IntegerDatum $left, DecimalDatum|IntegerDatum $right): DecimalDatum|IntegerDatum
    {
        return DecimalDatum::of(
            self::checked(self::unscaledOf($left) * self::unscaledOf($right)),
            self::scaleOf($left) + self::scaleOf($right),
        );
    }

    /**
     * Divides one exact number by another, cutting off what does not fit.
     *
     * @param DecimalDatum|IntegerDatum $left      The number divided
     * @param DecimalDatum|IntegerDatum $right     The number divided by
     * @param bool                      $asDecimal Whether to keep digits after the point even for two whole numbers, as an average does
     *
     * @example Two whole numbers divide into a whole number
     *     \App\Gql\Argument\ExactArithmetic::divide(new \App\Gql\Datum\IntegerDatum(7), new \App\Gql\Datum\IntegerDatum(2))->toText() // => '3'
     * @example A decimal on either side keeps six digits after the point
     *     \App\Gql\Argument\ExactArithmetic::divide(new \App\Gql\Datum\DecimalDatum(10, 1), new \App\Gql\Datum\IntegerDatum(3))->toText() // => '0.333333'
     * @example Nothing divides by zero
     *     \App\Gql\Argument\ExactArithmetic::divide(new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(0)) // throws \App\Gql\GqlException: division by zero
     * @example A quotient keeps fewer digits after the point rather than none at all
     *     \App\Gql\Argument\ExactArithmetic::divide(new \App\Gql\Datum\IntegerDatum(10000000000000), new \App\Gql\Datum\IntegerDatum(1), true)->toText() // => '10000000000000.0000'
     * @example The one quotient of two whole numbers that does not fit is out of range
     *     \App\Gql\Argument\ExactArithmetic::divide(new \App\Gql\Datum\IntegerDatum(PHP_INT_MIN), new \App\Gql\Datum\IntegerDatum(-1)) // throws \App\Gql\GqlException: numeric value out of range
     *
     * @return DecimalDatum|IntegerDatum The quotient
     *
     * @throws GqlException If the divisor is zero, or the quotient has more digits than an exact number can hold
     */
    public static function divide(DecimalDatum|IntegerDatum $left, DecimalDatum|IntegerDatum $right, bool $asDecimal = false): DecimalDatum|IntegerDatum
    {
        $divisor = self::unscaledOf($right);
        if ($divisor === 0) {
            throw GqlException::because(StatusCode::DivisionByZero, 'a number cannot be divided by zero');
        }
        $decimal = $asDecimal || $left instanceof DecimalDatum || $right instanceof DecimalDatum;
        $least = $decimal ? max(self::scaleOf($left), self::scaleOf($right)) : 0;
        $wanted = $decimal ? max($least, self::DIVISION_SCALE) : 0;
        $fits = self::room(self::unscaledOf($left)) - self::scaleOf($right) + self::scaleOf($left);
        $scale = max($least, min($wanted, $fits));
        $numerator = self::checked(self::unscaledOf($left) * self::power(self::scaleOf($right) + $scale - self::scaleOf($left)));
        if ($numerator === PHP_INT_MIN && $divisor === -1) {
            throw GqlException::because(StatusCode::NumericValueOutOfRange, 'the result has more digits than an exact number can hold');
        }

        return DecimalDatum::of(intdiv($numerator, $divisor), $scale);
    }

    /**
     * Returns how many places a whole number can be moved left before it no longer fits.
     *
     * @param int $unscaled The whole number
     *
     * @example A number of fourteen digits has room for four more
     *     \App\Gql\Argument\ExactArithmetic::room(10000000000000) // => 4
     * @example Nothing has room for eighteen
     *     \App\Gql\Argument\ExactArithmetic::room(0) // => 18
     *
     * @return int How many powers of ten it can be multiplied by, and still be held exactly
     */
    public static function room(int $unscaled): int
    {
        return $unscaled === 0 ? 18 : max(0, 18 - strlen(ltrim((string) $unscaled, '-')));
    }

    /**
     * Returns an exact number's digits at a larger scale.
     *
     * @param DecimalDatum|IntegerDatum $value The number
     * @param int                       $scale The scale, no smaller than the number's own
     *
     * @example One and a half at a scale of three is fifteen hundred
     *     \App\Gql\Argument\ExactArithmetic::scaled(new \App\Gql\Datum\DecimalDatum(15, 1), 3) // => 1500
     *
     * @return int The digits
     *
     * @throws GqlException If they have more digits than an exact number can hold
     */
    public static function scaled(DecimalDatum|IntegerDatum $value, int $scale): int
    {
        return self::checked(self::unscaledOf($value) * self::power($scale - self::scaleOf($value)));
    }

    /**
     * Returns a power of ten, refusing one too large to hold.
     *
     * @param int $exponent The power
     *
     * @example Ten to the third is a thousand
     *     \App\Gql\Argument\ExactArithmetic::power(3) // => 1000
     *
     * @return int The power of ten
     *
     * @throws GqlException If it is too large to hold
     */
    public static function power(int $exponent): int
    {
        return self::checked(10 ** $exponent);
    }

    /**
     * Returns a result that is still a whole number, refusing one that overflowed.
     *
     * PHP turns an integer that no longer fits into a float, which would make an exact
     * result quietly approximate. That is the one thing an exact number must not do.
     *
     * @param float|int $result What the arithmetic produced
     *
     * @example A result that fits is the result
     *     \App\Gql\Argument\ExactArithmetic::checked(42) // => 42
     * @example One that overflowed is out of range
     *     \App\Gql\Argument\ExactArithmetic::checked(1.0e30) // throws \App\Gql\GqlException: numeric value out of range
     *
     * @return int The result
     *
     * @throws GqlException If it overflowed
     */
    public static function checked(float|int $result): int
    {
        if (is_int($result)) {
            return $result;
        }

        throw GqlException::because(StatusCode::NumericValueOutOfRange, 'the result has more digits than an exact number can hold');
    }

    /**
     * Returns how many of an exact number's digits come after its point.
     *
     * @param DecimalDatum|IntegerDatum $value The number
     *
     * @example A whole number has none
     *     \App\Gql\Argument\ExactArithmetic::scaleOf(new \App\Gql\Datum\IntegerDatum(3)) // => 0
     *
     * @return int The scale
     */
    public static function scaleOf(DecimalDatum|IntegerDatum $value): int
    {
        return $value instanceof DecimalDatum ? $value->scale : 0;
    }

    /**
     * Returns an exact number's digits as one whole number.
     *
     * @param DecimalDatum|IntegerDatum $value The number
     *
     * @example A whole number is its own digits
     *     \App\Gql\Argument\ExactArithmetic::unscaledOf(new \App\Gql\Datum\IntegerDatum(3)) // => 3
     *
     * @return int The digits
     */
    public static function unscaledOf(DecimalDatum|IntegerDatum $value): int
    {
        return $value instanceof DecimalDatum ? $value->unscaled : $value->value;
    }
}
