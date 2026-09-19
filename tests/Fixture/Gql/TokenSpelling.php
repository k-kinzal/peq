<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\TokenKind;

/**
 * A query written out as the pieces it is read into.
 *
 * Comparing two lists of token objects tells a reader which one differs and nothing
 * about how; comparing two strings tells them both. Writing the pieces out is
 * therefore how a test about lexing says what it expects.
 */
final class TokenSpelling
{
    /**
     * Writes a query out as the pieces it is read into.
     *
     * @param string $query The query
     *
     * @return string The pieces, each named by its kind and followed by what it stands for
     */
    public static function of(string $query): string
    {
        $pieces = Lexer::over($query)->tokenize();

        $written = [];
        for ($place = 0; $pieces->at($place)->kind !== TokenKind::End; ++$place) {
            $written[] = $pieces->at($place)->kind->name.'('.$pieces->at($place)->value.')';
        }

        return implode(' ', $written);
    }
}
