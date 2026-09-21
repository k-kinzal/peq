<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * What a pattern requires of the labels an element carries.
 *
 * It is one shape holding an operator and its operands rather than a class per
 * operator, because a label expression is a small boolean formula and the only thing
 * anyone ever does with it is decide whether a set of labels satisfies it. Five
 * classes to answer one question would be four classes too many.
 *
 * Matching is covariant, as GQL requires: an element that carries more labels than
 * the pattern names still matches. A pattern asking for `Callable` matches a method,
 * which carries `Method`, `Member` and `Callable`.
 */
final readonly class LabelPattern
{
    /**
     * @param LabelOperator      $operator How the requirement is built up
     * @param null|string        $name     The label required, when the operator names one
     * @param list<LabelPattern> $operands The requirements it is built from, when it is built from any
     */
    public function __construct(
        public LabelOperator $operator,
        public ?string $name = null,
        public array $operands = [],
    ) {
        assert($this->operator !== LabelOperator::Named || ($this->name !== null && $this->name !== ''), 'A named requirement names a label');
    }

    /**
     * Requires a particular label.
     *
     * @param string $name The label
     *
     * @example A requirement can be a single label
     *     \App\Gql\Syntax\Pattern\LabelPattern::named('Method')->name // => 'Method'
     *
     * @return self The requirement
     */
    public static function named(string $name): self
    {
        return new self(LabelOperator::Named, $name);
    }

    /**
     * Requires nothing beyond carrying a label at all.
     *
     * @example Anything at all is a requirement every labelled element meets
     *     \App\Gql\Syntax\Pattern\LabelPattern::anything()->operator // => \App\Gql\Syntax\Pattern\LabelOperator::Anything
     *
     * @return self The requirement
     */
    public static function anything(): self
    {
        return new self(LabelOperator::Anything);
    }

    /**
     * Requires both of two things.
     *
     * @param self $left  The first requirement
     * @param self $right The second
     *
     * @example Both requirements have to be met
     *     $required = \App\Gql\Syntax\Pattern\LabelPattern::both(
     *         \App\Gql\Syntax\Pattern\LabelPattern::named('Member'),
     *         \App\Gql\Syntax\Pattern\LabelPattern::named('Callable'),
     *     );
     *     count($required->operands) // => 2
     *
     * @return self The requirement
     */
    public static function both(self $left, self $right): self
    {
        return new self(LabelOperator::Both, null, [$left, $right]);
    }

    /**
     * Requires at least one of two things.
     *
     * @param self $left  The first requirement
     * @param self $right The second
     *
     * @example Either requirement will do
     *     $required = \App\Gql\Syntax\Pattern\LabelPattern::either(
     *         \App\Gql\Syntax\Pattern\LabelPattern::named('Method'),
     *         \App\Gql\Syntax\Pattern\LabelPattern::named('Function'),
     *     );
     *     $required->operator // => \App\Gql\Syntax\Pattern\LabelOperator::Either
     *
     * @return self The requirement
     */
    public static function either(self $left, self $right): self
    {
        return new self(LabelOperator::Either, null, [$left, $right]);
    }

    /**
     * Requires that something is not the case.
     *
     * @param self $operand The requirement being refused
     *
     * @example A requirement can be refused
     *     $required = \App\Gql\Syntax\Pattern\LabelPattern::neither(\App\Gql\Syntax\Pattern\LabelPattern::named('Interface'));
     *     count($required->operands) // => 1
     *
     * @return self The requirement
     */
    public static function neither(self $operand): self
    {
        return new self(LabelOperator::Neither, null, [$operand]);
    }
}
