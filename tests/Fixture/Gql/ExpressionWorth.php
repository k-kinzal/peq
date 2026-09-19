<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\Datum;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\Syntax\Expression;

/**
 * What an expression written as text comes out as.
 *
 * Building an expression out of syntax objects takes five lines to say what one line
 * of GQL says, and a test that spends those five lines has hidden the thing it is
 * about. Writing the expression the way a query writes it, and reading the answer back
 * as text, leaves the test saying `'1 + 1.5'` comes out `'2.5'` — which is the whole
 * claim.
 */
final class ExpressionWorth
{
    /**
     * Works an expression out for a row and writes the answer as text.
     *
     * @param string               $written  The expression, as a query would write it
     * @param array<string, Datum> $bindings What the row binds, by name
     *
     * @return string The answer, written out
     *
     * @throws GqlException If the expression cannot be read or cannot be worked out
     */
    public static function of(string $written, array $bindings = []): string
    {
        return self::datum($written, $bindings)->toText();
    }

    /**
     * Works an expression out for a row.
     *
     * @param string               $written  The expression, as a query would write it
     * @param array<string, Datum> $bindings What the row binds, by name
     *
     * @return Datum The answer
     *
     * @throws GqlException If the expression cannot be read or cannot be worked out
     */
    public static function datum(string $written, array $bindings = []): Datum
    {
        return (new ExpressionEvaluation())->evaluate(self::parse($written), BindingRow::unit()->withAll($bindings));
    }

    /**
     * Works a summary out down a group of rows and writes the answer as text.
     *
     * @param string           $written The summary, as a query would write it
     * @param list<BindingRow> $group   The rows it summarises
     *
     * @return string The answer, written out
     *
     * @throws GqlException If the summary cannot be read or cannot be worked out
     */
    public static function over(string $written, array $group): string
    {
        $standing = $group === [] ? BindingRow::unit() : $group[0];

        return ExpressionEvaluation::over($group)->evaluate(self::parse($written), $standing)->toText();
    }

    /**
     * Reads an expression written the way a query writes one.
     *
     * @param string $written The expression
     *
     * @return Expression The expression
     *
     * @throws GqlException If what is written is not an expression
     */
    public static function parse(string $written): Expression
    {
        return (new ExpressionParser(TokenReader::of($written)))->parse();
    }
}
