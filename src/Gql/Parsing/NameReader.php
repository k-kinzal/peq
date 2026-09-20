<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\ReservedWords;

/**
 * Reading a name, and telling apart the three things GQL means by one.
 *
 * The grammar writes three productions where a reader sees a word, and they accept
 * different things:
 *
 * - a keyword — `upper` in `upper(x)` — is any word written plainly, reserved or not,
 *   because which words may stand there is settled by the production and not by GQL's
 *   reserved words;
 * - an `<identifier>` — a label, a property, a field, an alias — is a word the standard
 *   does not reserve, or any word at all in back quotes;
 * - a `<regular identifier>` — which is all a binding variable may be — is a word the
 *   standard does not reserve, and back quotes do not make one.
 *
 * Collapsing the three into "a name" is what lets `(:Function)` parse in an
 * implementation that means to be GQL, and it is why they are separated here rather
 * than left to each parser to remember.
 *
 * @visibility App\Gql
 */
final class NameReader
{
    /**
     * Reports whether a reader stands at a word written plainly.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A word is one whether or not GQL reserves it
     *     \App\Gql\Parsing\NameReader::atWord(\App\Gql\Parsing\TokenReader::of('upper(x)')) // => true
     * @example A name in back quotes is not a word
     *     \App\Gql\Parsing\NameReader::atWord(\App\Gql\Parsing\TokenReader::of('`upper`(x)')) // => false
     *
     * @return bool True when it does
     */
    public static function atWord(TokenReader $tokens): bool
    {
        return $tokens->current()->kind === TokenKind::Name;
    }

    /**
     * Reports whether a reader stands at a name GQL would let a query bind.
     *
     * This is what lets a pattern tell `(n:Method)` from `(:Method)` without a list of
     * special cases: `WHERE` is reserved, so `(n WHERE ...)` never looked like two
     * variables, and neither does any other keyword a later clause begins with.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A plain name could be bound
     *     \App\Gql\Parsing\NameReader::atVariable(\App\Gql\Parsing\TokenReader::of('p.firstName')) // => true
     * @example A word GQL reserves could not
     *     \App\Gql\Parsing\NameReader::atVariable(\App\Gql\Parsing\TokenReader::of('WHERE p.line > 1')) // => false
     *
     * @return bool True when it does
     */
    public static function atVariable(TokenReader $tokens): bool
    {
        $token = $tokens->current();

        return $token->kind === TokenKind::Name && !ReservedWords::reserves($token->value);
    }

    /**
     * Moves past an identifier: a label, a property, a field or an alias.
     *
     * A graph with a label called `Function` is matched by `` (:`Function`) ``, and
     * `(:Function)` is a query that was written wrong — `FUNCTION` is a word the
     * standard holds back for a later edition.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A name GQL does not reserve is an identifier
     *     \App\Gql\Parsing\NameReader::identifier(\App\Gql\Parsing\TokenReader::of('firstName')) // => 'firstName'
     * @example A word GQL reserves is one when it is written in back quotes
     *     \App\Gql\Parsing\NameReader::identifier(\App\Gql\Parsing\TokenReader::of('`value`')) // => 'value'
     * @example And is not one when it is not
     *     \App\Gql\Parsing\NameReader::identifier(\App\Gql\Parsing\TokenReader::of('value')) // throws \App\Gql\GqlException: reserves
     *
     * @return string The identifier, as it stands for itself
     *
     * @throws GqlException If the query does not have one here
     */
    public static function identifier(TokenReader $tokens): string
    {
        if (!$tokens->atName()) {
            $tokens->fail('a name');
        }
        $token = $tokens->current();
        if ($token->kind === TokenKind::Name && ReservedWords::reserves($token->value)) {
            self::refuseReserved($token, 'write it in back quotes to use it as a name');
        }

        return $tokens->take()->value;
    }

    /**
     * Moves past the name a query binds something to.
     *
     * A binding variable is narrower than an identifier in both directions: a reserved
     * word is not one, and neither is a name in back quotes. A variable is therefore
     * always a word a reader can read.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A name GQL does not reserve can be bound
     *     \App\Gql\Parsing\NameReader::variable(\App\Gql\Parsing\TokenReader::of('person')) // => 'person'
     * @example A word GQL reserves cannot, back quotes or not
     *     \App\Gql\Parsing\NameReader::variable(\App\Gql\Parsing\TokenReader::of('`value`')) // throws \App\Gql\GqlException: syntax error
     *
     * @return string The variable's name
     *
     * @throws GqlException If the query does not have one here
     */
    public static function variable(TokenReader $tokens): string
    {
        $token = $tokens->current();
        if ($token->kind === TokenKind::Name && ReservedWords::reserves($token->value)) {
            self::refuseReserved($token, 'a query binds a name to a word GQL leaves free');
        }
        if (!self::atVariable($tokens)) {
            $tokens->fail('a name');
        }

        return $tokens->take()->value;
    }

    /**
     * Reports that a word GQL reserves was written where a name was wanted.
     *
     * @param Token  $token  The word, and where it stands
     * @param string $remedy What the reader can write instead
     *
     * @example A reserved word is refused by name, and told what to write instead
     *     $token = \App\Gql\Parsing\TokenReader::of('value')->current();
     *     \App\Gql\Parsing\NameReader::refuseReserved($token, 'write it in back quotes') // throws \App\Gql\GqlException: GQL reserves "VALUE"
     *
     * @throws GqlException Always
     */
    public static function refuseReserved(Token $token, string $remedy): never
    {
        throw GqlException::syntax(
            'GQL reserves "'.strtoupper($token->value).'", so it is not a name here: '.$remedy,
            $token->line,
            $token->column,
            $token->describe(),
        );
    }
}
