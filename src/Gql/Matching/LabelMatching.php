<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;

/**
 * Whether the labels an element carries satisfy what a pattern requires of them.
 *
 * Matching is covariant, which is GQL's word for the thing that makes labels useful:
 * an element that carries more labels than the pattern names still matches. A pattern
 * asking for `Callable` matches a method, which carries `Method` and `Member` as well,
 * and a pattern asking for nothing matches everything.
 *
 * @visibility App\Gql
 */
final class LabelMatching
{
    /**
     * Reports whether a set of labels satisfies a requirement.
     *
     * @param null|LabelPattern $required What the pattern requires, or null when it requires nothing
     * @param list<string>      $labels   The labels the element carries
     *
     * @example A pattern that requires nothing is satisfied by anything
     *     \App\Gql\Matching\LabelMatching::satisfies(null, []) // => true
     * @example A label the element carries satisfies a requirement for it
     *     \App\Gql\Matching\LabelMatching::satisfies(\App\Gql\Syntax\Pattern\LabelPattern::named('Method'), ['Method', 'Callable']) // => true
     * @example A refusal is satisfied by an element that does not carry the label
     *     $required = \App\Gql\Syntax\Pattern\LabelPattern::neither(\App\Gql\Syntax\Pattern\LabelPattern::named('Interface'));
     *     \App\Gql\Matching\LabelMatching::satisfies($required, ['Class', 'ClassLike']) // => true
     *
     * @return bool True when the labels satisfy it
     */
    public static function satisfies(?LabelPattern $required, array $labels): bool
    {
        if ($required === null) {
            return true;
        }

        return match ($required->operator) {
            LabelOperator::Anything => $labels !== [],
            LabelOperator::Named => in_array($required->name, $labels, true),
            LabelOperator::Both => self::satisfies($required->operands[0] ?? null, $labels)
                && self::satisfies($required->operands[1] ?? null, $labels),
            LabelOperator::Either => self::satisfies($required->operands[0] ?? null, $labels)
                || self::satisfies($required->operands[1] ?? null, $labels),
            LabelOperator::Neither => !self::satisfies($required->operands[0] ?? null, $labels),
        };
    }
}
