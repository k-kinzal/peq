<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\GqlException;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\TokenReader;

/**
 * An expression written back out with every grouping made explicit.
 *
 * What a test about parsing an expression wants to say is which way the operators
 * bound, and the only readable way to say that is to write the tree back out with
 * every grouping parenthesised. `a OR b AND c` coming back as `(a OR (b AND c))` says
 * everything the test is about in one line.
 */
final class ExpressionSpelling
{
    /**
     * Reads an expression and writes it back out with every grouping explicit.
     *
     * @param string $written The expression, as a query would write it
     *
     * @return string The expression, with every grouping made explicit
     *
     * @throws GqlException If what is written is not an expression
     */
    public static function of(string $written): string
    {
        $reader = TokenReader::of($written);

        return QuerySpelling::expression((new ExpressionParser($reader))->parse());
    }
}
