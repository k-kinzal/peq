<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\GqlException;
use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Invocation\FunctionCatalog;
use App\Gql\Invocation\ListFunctions;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\IndexExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\VariableExpression;

/**
 * Working out what an expression is worth for a row.
 *
 * Almost all of it is unremarkable: read the row, apply the operator, call the
 * function. Two things are not.
 *
 * The first is that an evaluator may be standing over a group of rows rather than
 * one, and a summary written in that position summarises the group. The second is
 * that reading a property off a list reads it off every value in the list, which is
 * what makes `e.line` — where `e` is every edge a variable-length pattern crossed —
 * a list of lines rather than a mistake. Together they are how GQL's two kinds of
 * aggregation work: down a column of rows, and along a list within one.
 *
 * Which of the two a summary does is decided by what its argument turns out to be.
 * A summary over a list summarises the list; a summary over anything else
 * summarises the group. That is the standard's own precedence, and it is what makes
 * `min(count(e))` read the way it looks — the inner count along each path, the outer
 * minimum down the group.
 *
 * @visibility App\Gql
 */
final class ExpressionEvaluation
{
    /**
     * @param null|list<BindingRow> $group The rows a summary written here summarises, or null when there is no group
     */
    public function __construct(
        private readonly ?array $group = null,
    ) {}

    /**
     * Returns an evaluator standing over a group of rows.
     *
     * @param list<BindingRow> $group The rows
     *
     * @example A summary standing over rows summarises them
     *     $rows = [\App\Gql\Binding\BindingRow::unit(), \App\Gql\Binding\BindingRow::unit()];
     *     $counted = new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true);
     *     \App\Gql\Evaluation\ExpressionEvaluation::over($rows)->evaluate($counted, $rows[0])->toText() // => '2'
     *
     * @return self The evaluator
     */
    public static function over(array $group): self
    {
        return new self($group);
    }

    /**
     * Returns what an expression is worth for a row.
     *
     * @param Expression $expression The expression
     * @param BindingRow $row        The row it is worked out for
     *
     * @example A literal is worth what it says
     *     $written = new \App\Gql\Syntax\Expression\LiteralExpression(new \App\Gql\Datum\IntegerDatum(42));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate($written, \App\Gql\Binding\BindingRow::unit())->toText() // => '42'
     * @example A name is worth what the row bound it to
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(3));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate(new \App\Gql\Syntax\Expression\VariableExpression('n'), $row)->toText() // => '3'
     *
     * @return Datum What it is worth
     *
     * @throws GqlException If the expression cannot be worked out for this row
     */
    public function evaluate(Expression $expression, BindingRow $row): Datum
    {
        if ($expression instanceof LiteralExpression) {
            return $expression->value;
        }
        if ($expression instanceof VariableExpression) {
            return $this->bound($expression->name, $row);
        }
        if ($expression instanceof PropertyExpression) {
            return self::propertyOf($this->evaluate($expression->subject, $row), $expression->property);
        }
        if ($expression instanceof IndexExpression) {
            return $this->evaluateIndex($expression, $row);
        }
        if ($expression instanceof UnaryExpression) {
            return UnaryOperation::apply($expression->operator, $this->evaluate($expression->operand, $row));
        }
        if ($expression instanceof BinaryExpression) {
            return $this->evaluateBinary($expression, $row);
        }
        if ($expression instanceof ListExpression) {
            return new ListDatum(array_map(fn (Expression $item): Datum => $this->evaluate($item, $row), $expression->items));
        }
        if ($expression instanceof CaseExpression) {
            return $this->evaluateCase($expression, $row);
        }
        if ($expression instanceof CallExpression) {
            return $this->evaluateCall($expression, $row);
        }

        throw GqlException::because(StatusCode::UnknownFeature, 'this expression is not one peq knows how to work out');
    }

    /**
     * Returns what a name is bound to, reporting one that is not bound at all.
     *
     * An unbound name is a mistake in the query rather than an absent value: GQL
     * scopes names to the clauses after the one that bound them, so a name nothing
     * bound is a name the reader expected to mean something.
     *
     * @param string     $name The name
     * @param BindingRow $row  The row
     *
     * @example A name the row bound is worth what it was bound to
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(3));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->bound('n', $row)->toText() // => '3'
     * @example A name nothing bound is reported rather than read as absent
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->bound('p', \App\Gql\Binding\BindingRow::unit()) // throws \App\Gql\GqlException: invalid reference
     *
     * @return Datum What it is bound to
     *
     * @throws GqlException If nothing bound the name
     */
    public function bound(string $name, BindingRow $row): Datum
    {
        if (!$row->has($name)) {
            throw GqlException::because(StatusCode::InvalidReference, sprintf('nothing binds "%s" here', $name));
        }

        return $row->value($name);
    }

    /**
     * Returns a property read off a value.
     *
     * Reading a property off a list reads it off every value in the list. That is
     * what turns the edges a variable-length pattern bound into a list of their
     * lines, and it is the mechanism behind aggregating along a path.
     *
     * @param Datum  $subject The value the property is read off
     * @param string $name    The property name
     *
     * @example A property of a symbol is the property
     *     $node = new \App\Gql\Datum\NodeDatum('a', [], ['line' => new \App\Gql\Datum\IntegerDatum(12)]);
     *     \App\Gql\Evaluation\ExpressionEvaluation::propertyOf($node, 'line')->toText() // => '12'
     * @example A property of a list is the property of each of its values
     *     $first = new \App\Gql\Datum\NodeDatum('a', [], ['line' => new \App\Gql\Datum\IntegerDatum(1)]);
     *     $second = new \App\Gql\Datum\NodeDatum('b', [], ['line' => new \App\Gql\Datum\IntegerDatum(2)]);
     *     \App\Gql\Evaluation\ExpressionEvaluation::propertyOf(new \App\Gql\Datum\ListDatum([$first, $second]), 'line')->toText() // => '[1, 2]'
     * @example A property of something absent is absent
     *     \App\Gql\Evaluation\ExpressionEvaluation::propertyOf(new \App\Gql\Datum\NullDatum(), 'line')->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The property, or the absence of one
     *
     * @throws GqlException If the value has no properties to read
     */
    public static function propertyOf(Datum $subject, string $name): Datum
    {
        if ($subject instanceof NodeDatum || $subject instanceof EdgeDatum) {
            return $subject->property($name);
        }
        if ($subject instanceof ListDatum) {
            return new ListDatum(array_map(
                static fn (Datum $item): Datum => self::propertyOf($item, $name),
                $subject->items,
            ));
        }
        if ($subject->kind() === DatumKind::Null) {
            return new NullDatum();
        }

        throw GqlException::because(
            StatusCode::InvalidType,
            sprintf('a %s has no properties to read', $subject->kind()->typeName()),
        );
    }

    /**
     * Returns one value taken out of a list by its place in it.
     *
     * A place the list does not have reads as absent rather than as a mistake, which
     * is what lets `e[0]` be asked of a pattern that sometimes matched nothing.
     *
     * @param IndexExpression $expression The indexing
     * @param BindingRow      $row        The row it is worked out for
     *
     * @example A place past the end of a list is absent
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('e[5]'));
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('e', new \App\Gql\Datum\ListDatum([]));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate($parser->parse(), $row)->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The value, or the absence of one
     *
     * @throws GqlException If what is indexed is not a list, or the place is not a whole number
     */
    public function evaluateIndex(IndexExpression $expression, BindingRow $row): Datum
    {
        $subject = $this->evaluate($expression->subject, $row);
        $place = $this->evaluate($expression->index, $row);
        if ($subject->kind() === DatumKind::Null || $place->kind() === DatumKind::Null) {
            return new NullDatum();
        }
        if (!$place instanceof IntegerDatum) {
            throw GqlException::because(
                StatusCode::InvalidType,
                sprintf('a whole number was expected, and a %s was given', $place->kind()->typeName()),
            );
        }

        $items = ListFunctions::items($subject);

        return $items[$place->value] ?? new NullDatum();
    }

    /**
     * Returns what an operator written between two values is worth.
     *
     * The two connectives stop early when the side already read settles the answer.
     * GQL does not require that, but it is what makes `p.line IS NOT NULL AND 100 /
     * p.line > 2` a usable guard rather than a division by zero.
     *
     * @param BinaryExpression $expression The expression
     * @param BindingRow       $row        The row it is worked out for
     *
     * @example A conjunction whose left side is false does not read its right
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('FALSE AND 1 / 0 > 1'));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate($parser->parse(), \App\Gql\Binding\BindingRow::unit())->toText() // => 'FALSE'
     *
     * @return Datum What it is worth
     *
     * @throws GqlException If the values are not what the operator accepts
     */
    public function evaluateBinary(BinaryExpression $expression, BindingRow $row): Datum
    {
        $left = $this->evaluate($expression->left, $row);
        if ($expression->operator === BinaryOperator::And && Logic::truth($left) === false) {
            return Logic::datum(false);
        }
        if ($expression->operator === BinaryOperator::Or && Logic::truth($left) === true) {
            return Logic::datum(true);
        }

        return BinaryOperation::apply($expression->operator, $left, $this->evaluate($expression->right, $row));
    }

    /**
     * Returns what a choice between values is worth.
     *
     * A choice that matches nothing and was written without a fallback is worth
     * nothing, which is GQL's rule and the reason the fallback may be left out.
     *
     * @param CaseExpression $expression The choice
     * @param BindingRow     $row        The row it is worked out for
     *
     * @example A choice that matches nothing and offers no fallback is worth nothing
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("CASE WHEN FALSE THEN 'x' END"));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate($parser->parse(), \App\Gql\Binding\BindingRow::unit())->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum What it is worth
     *
     * @throws GqlException If a branch cannot be worked out for this row
     */
    public function evaluateCase(CaseExpression $expression, BindingRow $row): Datum
    {
        $subject = $expression->subject === null ? null : $this->evaluate($expression->subject, $row);
        foreach ($expression->branches as $branch) {
            $when = $this->evaluate($branch->when, $row);
            $taken = $subject === null
                ? Logic::truth($when)
                : Logic::truth(Comparison::apply(BinaryOperator::Equal, $subject, $when));
            if ($taken === true) {
                return $this->evaluate($branch->then, $row);
            }
        }

        return $expression->otherwise === null ? new NullDatum() : $this->evaluate($expression->otherwise, $row);
    }

    /**
     * Returns what a function call is worth.
     *
     * @param CallExpression $expression The call
     * @param BindingRow     $row        The row it is worked out for
     *
     * @example An ordinary function is applied to what it was given
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("upper('a')"));
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate($parser->parse(), \App\Gql\Binding\BindingRow::unit())->toText() // => 'A'
     *
     * @return Datum What it is worth
     *
     * @throws GqlException If the call cannot be made
     */
    public function evaluateCall(CallExpression $expression, BindingRow $row): Datum
    {
        if (AggregateCatalog::isAggregate($expression->name)) {
            return $this->evaluateSummary($expression, $row);
        }

        return FunctionCatalog::call(
            $expression->name,
            array_map(fn (Expression $argument): Datum => $this->evaluate($argument, $row), $expression->arguments),
        );
    }

    /**
     * Returns what a summary is worth: along a list, or down a group of rows.
     *
     * @param CallExpression $expression The summary
     * @param BindingRow     $row        The row it is worked out for
     *
     * @example A summary over a list summarises the list
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('max(e)'));
     *     $rows = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(4)]);
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('e', $rows);
     *     (new \App\Gql\Evaluation\ExpressionEvaluation())->evaluate($parser->parse(), $row)->toText() // => '4'
     * @example A summary over no rows at all summarises no rows, whatever it was written over
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of('count(DISTINCT caller)'));
     *     $evaluation = \App\Gql\Evaluation\ExpressionEvaluation::over([]);
     *     $evaluation->evaluate($parser->parse(), \App\Gql\Binding\BindingRow::unit())->toText() // => '0'
     *
     * @return Datum What it is worth
     *
     * @throws GqlException If the summary cannot be made here
     */
    public function evaluateSummary(CallExpression $expression, BindingRow $row): Datum
    {
        $plain = new self();
        if ($expression->star) {
            return $this->summaryOverRows($expression, null, $plain);
        }
        if (count($expression->arguments) !== 1) {
            throw GqlException::because(
                StatusCode::SyntaxError,
                sprintf('%s summarises one thing, and was given %d', $expression->name, count($expression->arguments)),
            );
        }

        $argument = $expression->arguments[0];
        if ($this->group === []) {
            return $this->summaryOverRows($expression, $argument, $plain);
        }

        $value = $plain->evaluate($argument, $row);
        if ($value instanceof ListDatum) {
            $summary = AggregateCatalog::start($expression->name, $expression->distinct, false);
            foreach ($value->items as $item) {
                $summary->accept($item);
            }

            return $summary->result();
        }
        if ($this->group === null) {
            $summary = AggregateCatalog::start($expression->name, $expression->distinct, false);
            $summary->accept($value);

            return $summary->result();
        }

        return $this->summaryOverRows($expression, $argument, $plain);
    }

    /**
     * Returns what a summary is worth down the group of rows this evaluator stands over.
     *
     * @param CallExpression  $expression The summary
     * @param null|Expression $argument   What is summarised, or null when rows themselves are
     * @param self            $plain      An evaluator for one row at a time
     *
     * @example A summary over rows counts the rows it stands over
     *     $rows = [\App\Gql\Binding\BindingRow::unit(), \App\Gql\Binding\BindingRow::unit()];
     *     $counted = new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true);
     *     $evaluation = \App\Gql\Evaluation\ExpressionEvaluation::over($rows);
     *     $evaluation->summaryOverRows($counted, null, new \App\Gql\Evaluation\ExpressionEvaluation())->toText() // => '2'
     *
     * @return Datum What it is worth
     *
     * @throws GqlException If the summary was written where there is no group to summarise
     */
    public function summaryOverRows(CallExpression $expression, ?Expression $argument, self $plain): Datum
    {
        if ($this->group === null) {
            throw GqlException::because(
                StatusCode::SyntaxError,
                sprintf('%s summarises rows, and can only be written where a projection produces them', $expression->name),
            );
        }

        $summary = AggregateCatalog::start($expression->name, $expression->distinct, $argument === null);
        foreach ($this->group as $groupRow) {
            $summary->accept($argument === null ? new NullDatum() : $plain->evaluate($argument, $groupRow));
        }

        return $summary->result();
    }
}
