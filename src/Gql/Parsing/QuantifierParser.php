<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\Datum\DecimalDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Pattern\Quantifier;

/**
 * Reads how often an edge pattern or a group repeats.
 *
 * @visibility App\Gql\Parsing
 */
final readonly class QuantifierParser
{
    /**
     * @param TokenReader $tokens The pieces of the query being read
     */
    public function __construct(
        private TokenReader $tokens,
    ) {}

    /**
     * Reads how often a pattern repeats, if a repetition is written here.
     *
     * GQL writes four: `*` and `+` for zero or more and one or more, `{n}` for exactly
     * `n`, and `{m,n}` with either bound left out. One with no upper bound — `*`, `+`,
     * `{m,}` — may be written only under a restrictor: without one, a path may revisit
     * what it has crossed, and the number of ways to repeat would have no end.
     *
     * @example An exact repetition bounds both ends the same way
     *     $parser = new \App\Gql\Parsing\QuantifierParser(\App\Gql\Parsing\TokenReader::of('{3}'));
     *     $parser->parse(false)?->most // => 3
     * @example A repetition with no upper bound is refused outside a restrictor
     *     $parser = new \App\Gql\Parsing\QuantifierParser(\App\Gql\Parsing\TokenReader::of('{1,}'));
     *     $parser->parse(false) // throws \App\Gql\GqlException: TRAIL
     * @example Under a restrictor it is read
     *     $parser = new \App\Gql\Parsing\QuantifierParser(\App\Gql\Parsing\TokenReader::of('*'));
     *     $parser->parse(true)?->most // => null
     * @example A pattern with no quantifier is crossed once
     *     $parser = new \App\Gql\Parsing\QuantifierParser(\App\Gql\Parsing\TokenReader::of('(b)'));
     *     $parser->parse(false) // => null
     *
     * @param bool $restricted Whether the path is under a restrictor, and so may repeat without end
     *
     * @return null|Quantifier The repetition, or null when none is written
     *
     * @throws GqlException If what is written is not a repetition, or repeats without end outside a restrictor
     */
    public function parse(bool $restricted): ?Quantifier
    {
        $written = $this->tokens->current();
        $quantifier = match (true) {
            $this->tokens->acceptSymbol('*') => new Quantifier(0, null),
            $this->tokens->acceptSymbol('+') => new Quantifier(1, null),
            $this->tokens->atSymbol('{') => $this->parseBraces(),
            default => null,
        };
        if ($quantifier !== null && $quantifier->most === null && !$restricted) {
            throw GqlException::syntax(
                'expected a restrictor before the path, since a quantifier with no upper bound repeats without end in a walk: write TRAIL, SIMPLE or ACYCLIC',
                $written->line,
                $written->column,
                $written->describe(),
            );
        }

        return $quantifier;
    }

    /**
     * Reads a quantifier written in braces.
     *
     * @example Both bounds may be written
     *     $parser = new \App\Gql\Parsing\QuantifierParser(\App\Gql\Parsing\TokenReader::of('{1,3}'));
     *     $parser->parseBraces()->least // => 1
     * @example A lower bound above the upper one is not a quantifier
     *     $parser = new \App\Gql\Parsing\QuantifierParser(\App\Gql\Parsing\TokenReader::of('{3,1}'));
     *     $parser->parseBraces() // throws \App\Gql\GqlException: syntax error
     *
     * @return Quantifier The repetition
     *
     * @throws GqlException If what is written is not one
     */
    public function parseBraces(): Quantifier
    {
        $this->tokens->expectSymbol('{');
        $least = $this->tokens->current()->kind === TokenKind::Integer ? DecimalDatum::whole($this->tokens->take()->value) : null;
        if (!$this->tokens->acceptSymbol(',')) {
            if ($least === null) {
                $this->tokens->fail('a number of repetitions');
            }
            $this->tokens->expectSymbol('}');

            return Quantifier::exactly($least);
        }

        $most = $this->tokens->current()->kind === TokenKind::Integer ? DecimalDatum::whole($this->tokens->take()->value) : null;
        if ($most !== null && $most < ($least ?? 0)) {
            $this->tokens->fail('an upper bound no lower than the lower one');
        }
        $this->tokens->expectSymbol('}');

        return new Quantifier($least ?? 0, $most);
    }

    /**
     * Reports whether the pieces of a path cross at least one edge.
     *
     * @param list<PathTerm> $terms The pieces
     *
     * @example A group of a single node crosses nothing
     *     \App\Gql\Parsing\QuantifierParser::crossesAnEdge([new \App\Gql\Syntax\Pattern\NodePattern()]) // => false
     *
     * @return bool True when one of them is an edge pattern, however deep
     */
    public static function crossesAnEdge(array $terms): bool
    {
        foreach ($terms as $term) {
            if ($term instanceof EdgePattern) {
                return true;
            }
            if ($term instanceof GroupPattern && self::crossesAnEdge($term->terms)) {
                return true;
            }
        }

        return false;
    }
}
