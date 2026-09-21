<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\ReservedWords;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\LabelPattern;

/**
 * Reads what a node or an edge pattern writes between its brackets.
 *
 * A node pattern and an edge pattern hold the same three things, in the same order —
 * the name the element is bound to, what its labels must be, and a predicate or the
 * properties it must carry — so they are read here once for both.
 *
 * @visibility App\Gql\Parsing
 */
final readonly class ElementParser
{
    /**
     * Reads what a pattern requires of an element's labels.
     */
    private LabelParser $labels;

    /**
     * @param TokenReader      $tokens      The pieces of the query being read
     * @param ExpressionParser $expressions Where a value or a predicate is written inside a pattern
     */
    public function __construct(
        private TokenReader $tokens,
        private ExpressionParser $expressions,
    ) {
        $this->labels = new LabelParser($tokens);
    }

    /**
     * Reads the name a matched element is bound to, if one is written here.
     *
     * What settles whether a name is written here is GQL's rule that a binding
     * variable is a word the standard does not reserve. That is why `(n WHERE ...)`
     * needs no special case: `WHERE` is reserved, so it was never a name.
     *
     * @example A name written first binds the element
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of('p:Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseName() // => 'p'
     * @example A word GQL reserves is not a name, and is refused where it stands as one
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of('function)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseName() // throws \App\Gql\GqlException: GQL reserves "FUNCTION"
     * @example A pattern that starts with a requirement binds nothing
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of(':Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseName() // => null
     *
     * @return null|string The name, or null when the match is not named
     *
     * @throws GqlException If the name cannot be read
     */
    public function parseName(): ?string
    {
        $token = $this->tokens->current();
        $next = $this->tokens->peek();
        $namedLikeAVariable = $next->isSymbol(')') || $next->isSymbol(']') || $next->isSymbol(':') || $next->isSymbol('{');
        if ($token->kind === TokenKind::Name && ReservedWords::reserves($token->value) && $namedLikeAVariable) {
            NameReader::refuseReserved($token, 'a query binds a name to a word GQL leaves free');
        }
        if (!NameReader::atVariable($this->tokens)) {
            return null;
        }

        return NameReader::variable($this->tokens);
    }

    /**
     * Reads what a pattern requires of an element's labels, if it requires anything.
     *
     * GQL's `<is or colon>` introduces the requirement, so `(n:Method)` and
     * `(n IS Method)` say the same.
     *
     * @example A requirement is written after a colon
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of(':Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseLabels()?->name // => 'Method'
     * @example A pattern with no colon requires nothing of them
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of(')'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseLabels() // => null
     *
     * @return null|LabelPattern The requirement, or null when none is written
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parseLabels(): ?LabelPattern
    {
        return $this->tokens->acceptSymbol(':') || $this->tokens->acceptKeyword('IS') ? $this->labels->parse() : null;
    }

    /**
     * Reads what a pattern requires of an element beyond its labels.
     *
     * GQL's `<element pattern predicate>` is a property specification or a `WHERE`
     * clause, not both: `(n {name: 'x'} WHERE n.line > 1)` is written
     * `(n WHERE n.name = 'x' AND n.line > 1)`.
     *
     * @example Properties are required to equal what they are written against
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of("{name: 'Invoice'})"), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     array_keys($parser->parseFilter()->properties) // => ['name']
     * @example A predicate is required to hold
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of('WHERE p.line > 10)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseFilter()->predicate !== null // => true
     *
     * @return ElementFilter The requirement
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parseFilter(): ElementFilter
    {
        if ($this->tokens->atSymbol('{')) {
            return new ElementFilter($this->parseProperties());
        }

        return new ElementFilter([], $this->tokens->acceptKeyword('WHERE') ? $this->expressions->parse() : null);
    }

    /**
     * Reads the properties a pattern requires an element to carry.
     *
     * @example Every property written has to equal what it is written against
     *     $parser = new \App\Gql\Parsing\ElementParser($tokens = \App\Gql\Parsing\TokenReader::of("{kind: 'method', visibility: 'public'}"), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     array_keys($parser->parseProperties()) // => ['kind', 'visibility']
     *
     * @return array<string, Expression> The properties, by name
     *
     * @throws GqlException If what is written is not a list of properties
     */
    public function parseProperties(): array
    {
        $this->tokens->expectSymbol('{');
        if ($this->tokens->acceptSymbol('}')) {
            return [];
        }

        $properties = [];
        do {
            $name = NameReader::identifier($this->tokens);
            $this->tokens->expectSymbol(':');
            $properties[$name] = $this->expressions->parse();
        } while ($this->tokens->acceptSymbol(','));
        $this->tokens->expectSymbol('}');

        return $properties;
    }
}
