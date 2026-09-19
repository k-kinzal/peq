<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\GqlException;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\TokenReader;

/**
 * A graph pattern written back out with every default made explicit.
 *
 * A test about reading a pattern wants to say what the drawing came out as, and the
 * readable way to say that is to write the pattern back out: `()->()` coming back as
 * `()-[]->()` says that the shortcut required nothing of the edge, and says it in one
 * line instead of an assertion five objects deep.
 */
final class PatternSpelling
{
    /**
     * Reads a pattern and writes it back out with every default made explicit.
     *
     * @param string $written The pattern, as a query would write it
     *
     * @return string The pattern, in one canonical form
     *
     * @throws GqlException If what is written is not a pattern
     */
    public static function of(string $written): string
    {
        return QuerySpelling::graph(self::parser($written)->parseGraph());
    }

    /**
     * Opens a reading of a pattern, for a test about one piece of the reading.
     *
     * @param string $written The pattern, as a query would write it
     *
     * @return PatternParser The reading, standing at the first piece
     *
     * @throws GqlException If what is written cannot be read at all
     */
    public static function parser(string $written): PatternParser
    {
        $tokens = TokenReader::of($written);

        return new PatternParser($tokens, new ExpressionParser($tokens));
    }
}
