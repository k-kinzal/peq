<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Pattern\Quantifier;

/**
 * Reads the drawing of a graph a query is written as.
 *
 * Graph patterns are what makes GQL worth using, and reading them is mostly a matter
 * of alternating: a thing that matches a node, then a thing that matches an edge, and
 * so on. The parts that are not obvious are the arrows, which the lexer deliberately
 * hands over in pieces so that `a < -1` stays arithmetic, and the decision between a
 * node pattern and a parenthesised group, which is made by looking one piece ahead.
 *
 * @visibility App\Gql
 */
final class PatternParser
{
    /**
     * Reads what a pattern requires of an element's labels.
     */
    private readonly LabelParser $labels;

    /**
     * @param TokenReader      $tokens      The pieces of the query being read
     * @param ExpressionParser $expressions Where a value or a predicate is written inside a pattern
     */
    public function __construct(
        private readonly TokenReader $tokens,
        private readonly ExpressionParser $expressions,
    ) {
        $this->labels = new LabelParser($tokens);
    }

    /**
     * Reads every path one MATCH looks for.
     *
     * @example Paths written side by side are matched together
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(a), (b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     count($parser->parseGraph()->paths) // => 2
     *
     * @return GraphPattern The pattern
     *
     * @throws GqlException If what is written is not a pattern
     */
    public function parseGraph(): GraphPattern
    {
        $paths = [];
        do {
            $paths[] = $this->parsePath();
        } while ($this->tokens->acceptSymbol(','));

        return new GraphPattern($paths);
    }

    /**
     * Reads one path, with the mode and the name it may be given.
     *
     * The name may be written before the mode or after it, because both readings are
     * unambiguous and a query should not have to remember which one this is.
     *
     * @example A path can be given a name to be returned under
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('p = (a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parsePath()->variable // => 'p'
     * @example A path can say what it may visit twice
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('ACYCLIC (a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parsePath()->mode // => \App\Gql\Syntax\Pattern\PathMode::Acyclic
     *
     * @return PathPattern The path
     *
     * @throws GqlException If what is written is not a path
     */
    public function parsePath(): PathPattern
    {
        $variable = $this->parsePathName();
        $mode = $this->parseMode();
        $variable ??= $this->parsePathName();

        return new PathPattern($this->parseTerms(), $mode, $variable);
    }

    /**
     * Reads the name a path is given, if one is written here.
     *
     * A name is only a path name when an equals sign follows it; otherwise it belongs
     * to whatever comes next.
     *
     * @example A name followed by an equals sign names the path
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('p = (a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parsePathName() // => 'p'
     * @example Anything else leaves the reading where it was
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parsePathName() // => null
     *
     * @return null|string The name, or null when the path is not named here
     *
     * @throws GqlException If the name cannot be read
     */
    public function parsePathName(): ?string
    {
        if (!NameReader::atVariable($this->tokens) || !$this->tokens->peek()->isSymbol('=')) {
            return null;
        }
        $name = NameReader::variable($this->tokens);
        $this->tokens->expectSymbol('=');

        return $name;
    }

    /**
     * Reads the mode a path is matched under, defaulting to the one GQL defaults to.
     *
     * A mode keyword followed by an equals sign is a variable that happens to spell
     * one, so it is left alone.
     *
     * @example A mode written before a path is read as the mode
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('SIMPLE (a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseMode() // => \App\Gql\Syntax\Pattern\PathMode::Simple
     * @example A path that says nothing crosses no edge twice
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseMode() // => \App\Gql\Syntax\Pattern\PathMode::Trail
     *
     * @return PathMode The mode
     */
    public function parseMode(): PathMode
    {
        foreach (PathMode::cases() as $mode) {
            if ($this->tokens->atKeyword($mode->value) && !$this->tokens->peek()->isSymbol('=')) {
                $this->tokens->take();

                return $mode;
            }
        }

        return PathMode::Trail;
    }

    /**
     * Reads the pieces of one path, alternating between nodes and edges.
     *
     * @example A path alternates between what matches a node and what matches an edge
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(a)-[:calls]->(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     count($parser->parseTerms()) // => 3
     *
     * @return list<PathTerm> The pieces, beginning and ending with one that matches a node
     *
     * @throws GqlException If what is written is not a path
     */
    public function parseTerms(): array
    {
        $terms = [$this->parseNodeOrGroup()];
        while ($this->tokens->atSymbol('-') || $this->tokens->atSymbol('<')) {
            $terms[] = $this->parseEdge();
            $terms[] = $this->parseNodeOrGroup();
        }

        return $terms;
    }

    /**
     * Reads either a node pattern or a parenthesised stretch of path.
     *
     * Both start with a parenthesis, and which one it is shows one piece later: a
     * group holds a path, and a path starts with a parenthesis of its own.
     *
     * @example A parenthesis holding a parenthesis is a group
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('((a)-[]->(b)){1,3}'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseNodeOrGroup() instanceof \App\Gql\Syntax\Pattern\GroupPattern // => true
     * @example One holding anything else is a node
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(p:Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseNodeOrGroup() instanceof \App\Gql\Syntax\Pattern\NodePattern // => true
     *
     * @return PathTerm The piece
     *
     * @throws GqlException If what is written is neither
     */
    public function parseNodeOrGroup(): PathTerm
    {
        if ($this->tokens->atSymbol('(') && $this->tokens->peek()->isSymbol('(')) {
            $this->tokens->expectSymbol('(');
            $terms = $this->parseTerms();
            $this->tokens->expectSymbol(')');

            return new GroupPattern($terms, $this->parseQuantifier());
        }

        return $this->parseNode();
    }

    /**
     * Reads what a pattern requires of a node.
     *
     * @example A node pattern binds a name and requires a label
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(p:Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseNode()->variable // => 'p'
     * @example A node pattern may require nothing at all
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('()'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseNode()->variable // => null
     *
     * @return NodePattern The requirement
     *
     * @throws GqlException If what is written is not a node pattern
     */
    public function parseNode(): NodePattern
    {
        $this->tokens->expectSymbol('(');
        $variable = $this->parseElementName();
        $labels = $this->parseLabels();
        $filter = $this->parseFilter();
        $this->tokens->expectSymbol(')');

        return new NodePattern($variable, $labels, $filter);
    }

    /**
     * Reads what a pattern requires of an edge, and which way it crosses it.
     *
     * The arrows arrive in pieces, so they are assembled here: a leading `<` means
     * the pattern reads the edge backwards, and a trailing `>` means it reads it
     * forwards. Neither means it does not care which way the edge points.
     *
     * @example An arrow pointing forward follows the edge
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('-[:calls]->(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->direction // => \App\Gql\Syntax\Pattern\EdgeDirection::Along
     * @example One pointing back reads it the other way
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('<-[:calls]-(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->direction // => \App\Gql\Syntax\Pattern\EdgeDirection::Against
     * @example A shortcut with no brackets requires nothing of the edge
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('->(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->labels // => null
     *
     * @return EdgePattern The requirement
     *
     * @throws GqlException If what is written is not an edge pattern
     */
    public function parseEdge(): EdgePattern
    {
        $backwards = $this->tokens->acceptSymbol('<');
        $this->tokens->expectSymbol('-');

        $variable = null;
        $labels = null;
        $filter = new ElementFilter();
        if ($this->tokens->acceptSymbol('[')) {
            $variable = $this->parseElementName();
            $labels = $this->parseLabels();
            $filter = $this->parseFilter();
            $this->tokens->expectSymbol(']');
            $this->tokens->expectSymbol('-');
        }

        $forwards = $this->tokens->acceptSymbol('>');
        $direction = match (true) {
            $backwards => EdgeDirection::Against,
            $forwards => EdgeDirection::Along,
            default => EdgeDirection::Either,
        };

        return new EdgePattern($direction, $variable, $labels, $filter, $this->parseQuantifier());
    }

    /**
     * Reads the name a matched element is bound to, if one is written here.
     *
     * What settles whether a name is written here is GQL's rule that a binding
     * variable is a word the standard does not reserve. That is why `(n WHERE ...)`
     * needs no special case: `WHERE` is reserved, so it was never a name.
     *
     * @example A name written first binds the element
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('p:Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseElementName() // => 'p'
     * @example A pattern that starts with a requirement binds nothing
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of(':Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseElementName() // => null
     *
     * @return null|string The name, or null when the match is not named
     *
     * @throws GqlException If the name cannot be read
     */
    public function parseElementName(): ?string
    {
        if (!NameReader::atVariable($this->tokens)) {
            return null;
        }

        return NameReader::variable($this->tokens);
    }

    /**
     * Reads what a pattern requires of an element's labels, if it requires anything.
     *
     * @example A requirement is written after a colon
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of(':Method)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseLabels()?->name // => 'Method'
     * @example A pattern with no colon requires nothing of them
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of(')'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseLabels() // => null
     *
     * @return null|LabelPattern The requirement, or null when none is written
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parseLabels(): ?LabelPattern
    {
        return $this->tokens->acceptSymbol(':') ? $this->labels->parse() : null;
    }

    /**
     * Reads what a pattern requires of an element beyond its labels.
     *
     * @example Properties are required to equal what they are written against
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of("{name: 'Invoice'})"), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     array_keys($parser->parseFilter()->properties) // => ['name']
     * @example A predicate is required to hold
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('WHERE p.line > 10)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseFilter()->predicate !== null // => true
     *
     * @return ElementFilter The requirement
     *
     * @throws GqlException If what is written is not a requirement
     */
    public function parseFilter(): ElementFilter
    {
        $properties = $this->tokens->atSymbol('{') ? $this->parseProperties() : [];
        $predicate = $this->tokens->acceptKeyword('WHERE') ? $this->expressions->parse() : null;

        return new ElementFilter($properties, $predicate);
    }

    /**
     * Reads the properties a pattern requires an element to carry.
     *
     * @example Every property written has to equal what it is written against
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of("{kind: 'method', visibility: 'public'}"), new \App\Gql\Parsing\ExpressionParser($tokens));
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

    /**
     * Reads how often a pattern repeats, if a repetition is written here.
     *
     * Both bounds may be left out. Written without a lower bound the pattern may
     * match nothing at all, which makes the two ends of the edge the same node;
     * written without an upper one it goes as far as the path mode allows.
     *
     * @example An exact repetition bounds both ends the same way
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('{3}'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseQuantifier()?->most // => 3
     * @example A repetition with no upper bound goes as far as it can
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('{1,}'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseQuantifier()?->most // => null
     * @example A pattern with no braces is crossed once
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseQuantifier() // => null
     *
     * @return null|Quantifier The repetition, or null when none is written
     *
     * @throws GqlException If what is written is not a repetition
     */
    public function parseQuantifier(): ?Quantifier
    {
        if (!$this->tokens->acceptSymbol('{')) {
            return null;
        }

        $least = 0;
        if ($this->tokens->current()->kind === TokenKind::Integer) {
            $least = (int) $this->tokens->take()->value;
        }

        $most = $least;
        if ($this->tokens->acceptSymbol(',')) {
            $most = $this->tokens->current()->kind === TokenKind::Integer
                ? (int) $this->tokens->take()->value
                : null;
        }
        $this->tokens->expectSymbol('}');

        return new Quantifier($least, $most);
    }
}
