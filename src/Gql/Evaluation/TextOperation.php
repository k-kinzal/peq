<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Argument\TextArgument;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Syntax\Expression\BinaryOperator;

/**
 * Joining strings, and the three ways GQL asks whether one is inside another.
 *
 * The string predicates are the ones a question about code reaches for most: a
 * namespace is a prefix, a suffix names a convention — `Controller`, `Repository` —
 * and a substring is how a reader looks for a word in a path. They are operators
 * rather than functions in GQL, which is why they are here rather than among the
 * built-in functions.
 *
 * Joining is lenient about what it joins. GQL's `||` is defined over strings, but a
 * query that writes `'line ' || p.line` means exactly what it looks like, and
 * refusing it would only make the reader wrap it in a conversion. Two lists joined
 * produce a list, which is the other thing `||` means in the standard.
 *
 * @visibility App\Gql
 */
final class TextOperation
{
    /**
     * Applies a string operator to two values.
     *
     * @param BinaryOperator $operator What to apply
     * @param Datum          $left     The value on its left
     * @param Datum          $right    The value on its right
     *
     * @example A namespace is asked for as a prefix
     *     $name = new \App\Gql\Datum\StringDatum('App\\Domain\\Invoice');
     *     \App\Gql\Evaluation\TextOperation::apply(\App\Gql\Syntax\Expression\BinaryOperator::StartsWith, $name, new \App\Gql\Datum\StringDatum('App\\Domain'))->toText() // => 'TRUE'
     * @example A convention is asked for as a suffix
     *     $name = new \App\Gql\Datum\StringDatum('UserController');
     *     \App\Gql\Evaluation\TextOperation::apply(\App\Gql\Syntax\Expression\BinaryOperator::EndsWith, $name, new \App\Gql\Datum\StringDatum('Controller'))->toText() // => 'TRUE'
     * @example Anything asked of an absent value is undecided
     *     $absent = new \App\Gql\Datum\NullDatum();
     *     \App\Gql\Evaluation\TextOperation::apply(\App\Gql\Syntax\Expression\BinaryOperator::Contains, $absent, new \App\Gql\Datum\StringDatum('x'))->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum What the operator produced
     *
     * @throws GqlException If a value cannot be read as a string
     */
    public static function apply(BinaryOperator $operator, Datum $left, Datum $right): Datum
    {
        if ($left->kind() === DatumKind::Null || $right->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        if ($operator === BinaryOperator::Concatenate) {
            return self::concatenate($left, $right);
        }

        $subject = TextArgument::of($left);
        $sought = TextArgument::of($right);
        if ($operator === BinaryOperator::Contains) {
            return Logic::datum(str_contains($subject, $sought));
        }

        return Logic::datum($operator === BinaryOperator::StartsWith
            ? str_starts_with($subject, $sought)
            : str_ends_with($subject, $sought));
    }

    /**
     * Joins two values, as strings or as lists.
     *
     * @param Datum $left  The value on the left
     * @param Datum $right The value on the right
     *
     * @example Two strings join into a longer one
     *     \App\Gql\Evaluation\TextOperation::concatenate(new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\StringDatum('b'))->toText() // => 'ab'
     * @example Two lists join into a longer one
     *     $left = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1)]);
     *     $right = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(2)]);
     *     \App\Gql\Evaluation\TextOperation::concatenate($left, $right)->toText() // => '[1, 2]'
     *
     * @return Datum The two joined
     */
    public static function concatenate(Datum $left, Datum $right): Datum
    {
        if ($left instanceof ListDatum && $right instanceof ListDatum) {
            return new ListDatum([...$left->items, ...$right->items]);
        }

        return new StringDatum($left->toText().$right->toText());
    }
}
