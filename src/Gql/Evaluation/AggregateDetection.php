<?php

declare(strict_types=1);

namespace App\Gql\Evaluation;

use App\Gql\Invocation\AggregateCatalog;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\IndexExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;

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
 * @visibility App\Gql
 */
final class AggregateDetection
{
    /**
     * Reports whether an expression summarises rows anywhere inside it.
     *
     * @param Expression $expression The expression
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
     *
     * @return bool True when it holds a summary
     */
    public static function within(Expression $expression): bool
    {
        if ($expression instanceof CallExpression) {
            return AggregateCatalog::isAggregate($expression->name) || self::withinAny($expression->arguments);
        }
        if ($expression instanceof BinaryExpression) {
            return self::within($expression->left) || self::within($expression->right);
        }
        if ($expression instanceof UnaryExpression) {
            return self::within($expression->operand);
        }
        if ($expression instanceof PropertyExpression) {
            return self::within($expression->subject);
        }
        if ($expression instanceof IndexExpression) {
            return self::within($expression->subject) || self::within($expression->index);
        }
        if ($expression instanceof ListExpression) {
            return self::withinAny($expression->items);
        }
        if ($expression instanceof CaseExpression) {
            return self::withinCase($expression);
        }

        return false;
    }

    /**
     * Reports whether any of several expressions summarises rows.
     *
     * @param list<Expression> $expressions The expressions
     *
     * @example Nothing at all summarises nothing
     *     \App\Gql\Evaluation\AggregateDetection::withinAny([]) // => false
     *
     * @return bool True when at least one of them does
     */
    public static function withinAny(array $expressions): bool
    {
        foreach ($expressions as $expression) {
            if (self::within($expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a choice between values summarises rows anywhere inside it.
     *
     * @param CaseExpression $expression The choice
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
    public static function withinCase(CaseExpression $expression): bool
    {
        $parts = $expression->subject === null ? [] : [$expression->subject];
        foreach ($expression->branches as $branch) {
            $parts[] = $branch->when;
            $parts[] = $branch->then;
        }
        if ($expression->otherwise !== null) {
            $parts[] = $expression->otherwise;
        }

        return self::withinAny($parts);
    }
}
