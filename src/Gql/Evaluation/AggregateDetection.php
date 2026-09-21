<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\VariableExpression;

/**
 * Whether a projection summarises its rows or reports them.
 *
 * This is the one question that has to be answered before a projection is computed
 * rather than while computing it, because the two produce different numbers of rows:
 * `RETURN p.name` gives one row per match, and `RETURN count(*)` gives one row for all
 * of them. Nothing about a single row can tell you which was meant — only the shape
 * of the expression can.
 *
 * The answer is about the whole expression, however deeply the summary is buried:
 * `RETURN 'found ' || count(*)` summarises, and so does `RETURN CASE WHEN count(*) > 1
 * THEN 'many' ELSE 'one' END`.
 *
 * Not every summary summarises rows, though, and that is the part worth reading twice.
 * GQL has two kinds of aggregation, and a summary written over a group list — the name
 * a repeating edge pattern binds to every relation it crossed — summarises *along* the
 * row rather than down the table. `min(e.line)` is the earliest line of this path, and
 * asking it of four paths should give four answers rather than one. The standard puts
 * it plainly: horizontal aggregation takes precedence over vertical aggregation. So a
 * summary over a group list is passed over here, and the projection that holds nothing
 * else keeps one row per match.
 *
 * @visibility App\Gql
 */
final class AggregateDetection
{
    /**
     * Reports whether an expression summarises rows anywhere inside it.
     *
     * @param Expression   $expression The expression
     * @param list<string> $groupLists The names a repeating pattern bound to every relation it crossed
     *
     * @example A projection that counts summarises
     *     \App\Gql\Evaluation\AggregateDetection::within(new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true)) // => true
     * @example One that reads a property does not
     *     $subject = new \App\Gql\Syntax\Expression\VariableExpression('p');
     *     \App\Gql\Evaluation\AggregateDetection::within(new \App\Gql\Syntax\Expression\PropertyExpression($subject, 'name')) // => false
     * @example A summary buried inside an expression still summarises
     *     $counted = new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true);
     *     $written = new \App\Gql\Syntax\Expression\BinaryExpression(
     *         \App\Gql\Syntax\Expression\BinaryOperator::Greater,
     *         $counted,
     *         new \App\Gql\Syntax\Expression\LiteralExpression(new \App\Gql\Datum\IntegerDatum(1)),
     *     );
     *     \App\Gql\Evaluation\AggregateDetection::within($written) // => true
     * @example A summary over a group list summarises the row rather than the rows
     *     $edges = new \App\Gql\Syntax\Expression\PropertyExpression(new \App\Gql\Syntax\Expression\VariableExpression('e'), 'line');
     *     \App\Gql\Evaluation\AggregateDetection::within(new \App\Gql\Syntax\Expression\CallExpression('min', [$edges]), ['e']) // => false
     *
     * @return bool True when it holds a summary of the rows
     */
    public static function within(Expression $expression, array $groupLists = []): bool
    {
        if ($expression instanceof CallExpression) {
            return (AggregateCatalog::isAggregate($expression->name) && !self::alongTheRow($expression, $groupLists))
                || self::withinAny($expression->arguments, $groupLists);
        }
        if ($expression instanceof BinaryExpression) {
            return self::within($expression->left, $groupLists) || self::within($expression->right, $groupLists);
        }
        if ($expression instanceof UnaryExpression) {
            return self::within($expression->operand, $groupLists);
        }
        if ($expression instanceof PropertyExpression) {
            return self::within($expression->subject, $groupLists);
        }
        if ($expression instanceof ListExpression) {
            return self::withinAny($expression->items, $groupLists);
        }
        if ($expression instanceof CaseExpression) {
            return self::withinCase($expression, $groupLists);
        }

        return false;
    }

    /**
     * Reports whether a summary is written over a group list rather than over rows.
     *
     * @param CallExpression $expression The summary
     * @param list<string>   $groupLists The names a repeating pattern bound to every relation it crossed
     *
     * @example A summary of a property of a group list is written along the row
     *     $edges = new \App\Gql\Syntax\Expression\PropertyExpression(new \App\Gql\Syntax\Expression\VariableExpression('e'), 'line');
     *     \App\Gql\Evaluation\AggregateDetection::alongTheRow(new \App\Gql\Syntax\Expression\CallExpression('min', [$edges]), ['e']) // => true
     * @example A summary of anything else is written down the rows
     *     $line = new \App\Gql\Syntax\Expression\PropertyExpression(new \App\Gql\Syntax\Expression\VariableExpression('p'), 'line');
     *     \App\Gql\Evaluation\AggregateDetection::alongTheRow(new \App\Gql\Syntax\Expression\CallExpression('min', [$line]), ['e']) // => false
     * @example So is a count of the rows themselves
     *     \App\Gql\Evaluation\AggregateDetection::alongTheRow(new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true), ['e']) // => false
     *
     * @return bool True when it summarises one row's group list
     */
    public static function alongTheRow(CallExpression $expression, array $groupLists): bool
    {
        if ($expression->star || count($expression->arguments) !== 1) {
            return false;
        }
        $rooted = self::rootOf($expression->arguments[0]);

        return $rooted !== null && in_array($rooted, $groupLists, true);
    }

    /**
     * Returns the name an expression ultimately reads from, when it reads from one.
     *
     * @param Expression $expression The expression
     *
     * @example A name reads from itself
     *     \App\Gql\Evaluation\AggregateDetection::rootOf(new \App\Gql\Syntax\Expression\VariableExpression('e')) // => 'e'
     * @example A property reads from whatever its subject reads from
     *     $subject = new \App\Gql\Syntax\Expression\VariableExpression('e');
     *     \App\Gql\Evaluation\AggregateDetection::rootOf(new \App\Gql\Syntax\Expression\PropertyExpression($subject, 'line')) // => 'e'
     * @example Anything else reads from nothing in particular
     *     \App\Gql\Evaluation\AggregateDetection::rootOf(new \App\Gql\Syntax\Expression\CallExpression('count', [], false, true)) // => null
     *
     * @return null|string The name, or null when the expression reads from no single one
     */
    public static function rootOf(Expression $expression): ?string
    {
        if ($expression instanceof VariableExpression) {
            return $expression->name;
        }
        if ($expression instanceof PropertyExpression) {
            return self::rootOf($expression->subject);
        }

        return null;
    }

    /**
     * Reports whether any of several expressions summarises rows.
     *
     * @param list<Expression> $expressions The expressions
     * @param list<string>     $groupLists  The names a repeating pattern bound to every relation it crossed
     *
     * @example Nothing at all summarises nothing
     *     \App\Gql\Evaluation\AggregateDetection::withinAny([]) // => false
     *
     * @return bool True when at least one of them does
     */
    public static function withinAny(array $expressions, array $groupLists = []): bool
    {
        foreach ($expressions as $expression) {
            if (self::within($expression, $groupLists)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a choice between values summarises rows anywhere inside it.
     *
     * @param CaseExpression $expression The choice
     * @param list<string>   $groupLists The names a repeating pattern bound to every relation it crossed
     *
     * @example A choice whose branches read properties does not summarise
     *     $branch = new \App\Gql\Syntax\Expression\CaseBranch(
     *         new \App\Gql\Syntax\Expression\VariableExpression('a'),
     *         new \App\Gql\Syntax\Expression\VariableExpression('b'),
     *     );
     *     \App\Gql\Evaluation\AggregateDetection::withinCase(new \App\Gql\Syntax\Expression\CaseExpression(null, [$branch])) // => false
     *
     * @return bool True when it holds a summary
     */
    public static function withinCase(CaseExpression $expression, array $groupLists = []): bool
    {
        $parts = $expression->subject === null ? [] : [$expression->subject];
        foreach ($expression->branches as $branch) {
            $parts[] = $branch->when;
            $parts[] = $branch->then;
        }
        if ($expression->otherwise !== null) {
            $parts[] = $expression->otherwise;
        }

        return self::withinAny($parts, $groupLists);
    }
}
