<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Syntax\Pattern\LabelPattern;

/**
 * Reads what a pattern requires of an element's labels.
 *
 * The grammar is a small boolean one, and its binding order is GQL's: negation
 * tightest, then conjunction, then disjunction, so `!A&B|C` is `((!A)&B)|C`.
 * Parentheses override it, as everywhere else.
 *
 * @visibility App\Gql\Parsing
 */
final class LabelParser
{
    /**
     * @param TokenReader $tokens The pieces of the query being read
     */
    public function __construct(
        private readonly TokenReader $tokens,
    ) {}

    /**
     * Reads a requirement, disjunction being the loosest thing it can be.
     *
     * @example Either of two labels will do
     *     $parsed = (new \App\Gql\Parsing\LabelParser(\App\Gql\Parsing\TokenReader::of('Method|`Function`')))->parse();
     *     $parsed->operator // => \App\Gql\Syntax\Pattern\LabelOperator::Either
     *
     * @return LabelPattern The requirement
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parse(): LabelPattern
    {
        $left = $this->parseBoth();
        while ($this->tokens->acceptSymbol('|')) {
            $left = LabelPattern::either($left, $this->parseBoth());
        }

        return $left;
    }

    /**
     * Reads a conjunction of requirements.
     *
     * @example Conjunction binds tighter than disjunction
     *     $parsed = (new \App\Gql\Parsing\LabelParser(\App\Gql\Parsing\TokenReader::of('Member&Callable')))->parse();
     *     $parsed->operator // => \App\Gql\Syntax\Pattern\LabelOperator::Both
     *
     * @return LabelPattern The requirement
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parseBoth(): LabelPattern
    {
        $left = $this->parseSingle();
        while ($this->tokens->acceptSymbol('&')) {
            $left = LabelPattern::both($left, $this->parseSingle());
        }

        return $left;
    }

    /**
     * Reads one requirement: a label, a refusal, a wildcard, or a parenthesised group.
     *
     * @example A refusal binds tightest of all
     *     $parsed = (new \App\Gql\Parsing\LabelParser(\App\Gql\Parsing\TokenReader::of('!Interface')))->parse();
     *     $parsed->operator // => \App\Gql\Syntax\Pattern\LabelOperator::Neither
     * @example A wildcard asks for nothing in particular
     *     $parsed = (new \App\Gql\Parsing\LabelParser(\App\Gql\Parsing\TokenReader::of('%')))->parse();
     *     $parsed->operator // => \App\Gql\Syntax\Pattern\LabelOperator::Anything
     *
     * @return LabelPattern The requirement
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parseSingle(): LabelPattern
    {
        if ($this->tokens->acceptSymbol('!')) {
            return LabelPattern::neither($this->parseSingle());
        }
        if ($this->tokens->acceptSymbol('%')) {
            return LabelPattern::anything();
        }
        if ($this->tokens->acceptSymbol('(')) {
            $inner = $this->parse();
            $this->tokens->expectSymbol(')');

            return $inner;
        }

        return LabelPattern::named(NameReader::identifier($this->tokens));
    }
}
