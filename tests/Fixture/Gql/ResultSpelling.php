<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\GqlException;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\ResultParser;
use App\Gql\Parsing\TokenReader;

/**
 * A projection written back out with every default made explicit.
 *
 * `RETURN` carries the grouping, the ordering and the paging of a result as well as
 * its columns, so a test about reading one has four things to say at once. Writing the
 * projection back out says all four in a line: `RETURN a AS a ORDER BY a ASC PAGE
 * off=0 limit=10` leaves nothing for the reader of the test to guess at.
 */
final class ResultSpelling
{
    /**
     * Reads a projection and writes it back out with every default made explicit.
     *
     * @param string $written The projection, as a query would write it
     *
     * @return string The projection, in one canonical form
     *
     * @throws GqlException If what is written is not a projection
     */
    public static function of(string $written): string
    {
        return QuerySpelling::projection(self::parser($written)->parse());
    }

    /**
     * Opens a reading of a projection, for a test about one piece of the reading.
     *
     * @param string $written The projection, as a query would write it
     *
     * @return ResultParser The reading, standing at the first piece
     *
     * @throws GqlException If what is written cannot be read at all
     */
    public static function parser(string $written): ResultParser
    {
        $tokens = TokenReader::of($written);

        return new ResultParser($tokens, new ExpressionParser($tokens));
    }
}
