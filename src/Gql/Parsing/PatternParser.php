<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\PathTerm;

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
     * Reads what a node or an edge pattern writes between its brackets.
     */
    private readonly ElementParser $elements;

    /**
     * Reads how often a pattern repeats.
     */
    private readonly QuantifierParser $quantifiers;

    /**
     * Whether the path being read is under a restrictor, and may repeat without end.
     */
    private bool $restricted = false;

    /**
     * @param TokenReader      $tokens      The pieces of the query being read
     * @param ExpressionParser $expressions Where a value or a predicate is written inside a pattern
     */
    public function __construct(
        private readonly TokenReader $tokens,
        ExpressionParser $expressions,
    ) {
        $this->elements = new ElementParser($tokens, $expressions);
        $this->quantifiers = new QuantifierParser($tokens);
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
        $this->restricted = $mode !== PathMode::Walk;

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
     * Reads the mode a path is matched under.
     *
     * A path that names no mode is a walk: GQL's restrictors — `TRAIL`, `SIMPLE`,
     * `ACYCLIC` — are things a query adds, and without one a path may repeat both nodes
     * and edges. That is also why a walk cannot repeat without end: GQL requires every
     * unbounded quantifier to stand under a restrictor or a selector, so that the
     * number of matches is finite.
     *
     * A mode keyword followed by an equals sign is a variable that happens to spell
     * one, so it is left alone.
     *
     * @example A mode written before a path is read as the mode
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('SIMPLE (a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseMode() // => \App\Gql\Syntax\Pattern\PathMode::Simple
     * @example A path that names no mode is a walk
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseMode() // => \App\Gql\Syntax\Pattern\PathMode::Walk
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

        return PathMode::Walk;
    }

    /**
     * Reads the pieces of one path.
     *
     * GQL writes a path as any sequence of node patterns, edge patterns and
     * parenthesised paths — `<path concatenation> ::= <path term> <path factor>` — so
     * `(s)((a)-[]->(b)){2}` and `-[e]->` are paths as much as `(a)-[e]->(b)` is. Two
     * node patterns side by side are the same node; an edge pattern with no node
     * pattern beside it has an anonymous one there, which is what GQL means by it and
     * what lets every path be matched as node, edge, node.
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
        $terms = [];
        while (true) {
            if ($this->tokens->atSymbol('(')) {
                $terms[] = $this->parseNodeOrGroup();

                continue;
            }
            if (!$this->atEdge()) {
                break;
            }
            if ($terms === [] || $terms[count($terms) - 1] instanceof EdgePattern) {
                $terms[] = new NodePattern();
            }
            $terms[] = $this->parseEdge();
        }
        if ($terms === []) {
            $this->tokens->fail('a path pattern');
        }
        if ($terms[count($terms) - 1] instanceof EdgePattern) {
            $terms[] = new NodePattern();
        }

        return $terms;
    }

    /**
     * Reports whether an edge pattern starts here.
     *
     * @example An arrow starts one
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('<-(a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->atEdge() // => true
     * @example A parenthesis does not
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('(a)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->atEdge() // => false
     *
     * @return bool True when one does
     */
    public function atEdge(): bool
    {
        return $this->tokens->atSymbol('-') || $this->tokens->atSymbol('<') || $this->tokens->atSymbol('~');
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
        $inside = $this->tokens->peek();
        if ($this->tokens->atSymbol('(') && ($inside->isSymbol('(') || $inside->isSymbol('-') || $inside->isSymbol('<') || $inside->isSymbol('~'))) {
            $this->tokens->expectSymbol('(');
            $terms = $this->parseTerms();
            $this->tokens->expectSymbol(')');
            $repeating = $this->tokens->current();
            $quantifier = $this->quantifiers->parse($this->restricted);
            if ($quantifier !== null && $quantifier->most === null && !QuantifierParser::crossesAnEdge($terms)) {
                throw GqlException::because(
                    StatusCode::UnknownFeature,
                    sprintf(
                        'a group that crosses no relation repeats without end at line %d, column %d, and peq repeats only what makes progress',
                        $repeating->line,
                        $repeating->column,
                    ),
                );
            }

            return new GroupPattern($terms, $quantifier);
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
        $variable = $this->elements->parseName();
        $labels = $this->elements->parseLabels();
        $filter = $this->elements->parseFilter();
        $this->tokens->expectSymbol(')');

        return new NodePattern($variable, $labels, $filter);
    }

    /**
     * Reads what a pattern requires of an edge, and which way it crosses it.
     *
     * GQL writes seven edge patterns, each in a full form with brackets and an
     * abbreviated one without: `<-[ ]-`, `-[ ]->` and `<-[ ]->` for directed edges read
     * backwards, forwards or either way, `-[ ]-` for any edge either way, `~[ ]~` for an
     * undirected edge, and `<~[ ]~`, `~[ ]~>` for an undirected edge or a directed one
     * read backwards or forwards. The arrows arrive in pieces — a leading `<`, a `-` or a
     * `~`, a trailing `>` — so they are assembled here.
     *
     * @example An arrow pointing forward follows the edge
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('-[:calls]->(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->direction // => \App\Gql\Syntax\Pattern\EdgeDirection::Along
     * @example One pointing back reads it the other way
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('<-[:calls]-(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->direction // => \App\Gql\Syntax\Pattern\EdgeDirection::Against
     * @example One pointing both ways reads it either way
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('<-[:calls]->(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->direction // => \App\Gql\Syntax\Pattern\EdgeDirection::Either
     * @example A tilde asks for an undirected edge
     *     $parser = new \App\Gql\Parsing\PatternParser($tokens = \App\Gql\Parsing\TokenReader::of('~(b)'), new \App\Gql\Parsing\ExpressionParser($tokens));
     *     $parser->parseEdge()->direction // => \App\Gql\Syntax\Pattern\EdgeDirection::Undirected
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
        $arrow = $this->tokens->current();
        $backwards = $this->tokens->acceptSymbol('<');
        $undirected = $this->tokens->acceptSymbol('~');
        if (!$undirected) {
            $this->tokens->expectSymbol('-');
        }

        $variable = null;
        $labels = null;
        $filter = new ElementFilter();
        if ($this->tokens->acceptSymbol('[')) {
            $variable = $this->elements->parseName();
            $labels = $this->elements->parseLabels();
            $filter = $this->elements->parseFilter();
            $this->tokens->expectSymbol(']');
            $this->tokens->expectSymbol($undirected ? '~' : '-');
        }

        $forwards = $this->tokens->acceptSymbol('>');
        if ($undirected && $backwards && $forwards) {
            throw GqlException::syntax('expected an edge pattern, and GQL writes none that is both <~ and ~>', $arrow->line, $arrow->column, $arrow->describe());
        }

        return new EdgePattern(self::direction($backwards, $undirected, $forwards), $variable, $labels, $filter, $this->quantifiers->parse($this->restricted));
    }

    /**
     * Works out which edges an edge pattern crosses, from how its arrow is drawn.
     *
     * An undirected edge is one peq's graph never has, so the forms that accept one
     * alongside a directed one cross exactly the directed ones.
     *
     * @param bool $backwards  Whether the arrow starts with `<`
     * @param bool $undirected Whether it is drawn with `~` rather than `-`
     * @param bool $forwards   Whether it ends with `>`
     *
     * @example `~[ ]~>` crosses what `-[ ]->` crosses, since no edge is undirected
     *     \App\Gql\Parsing\PatternParser::direction(false, true, true) // => \App\Gql\Syntax\Pattern\EdgeDirection::Along
     *
     * @return EdgeDirection The direction
     */
    public static function direction(bool $backwards, bool $undirected, bool $forwards): EdgeDirection
    {
        return match (true) {
            $backwards && $forwards, !$backwards && !$forwards && !$undirected => EdgeDirection::Either,
            $backwards => EdgeDirection::Against,
            $forwards => EdgeDirection::Along,
            default => EdgeDirection::Undirected,
        };
    }
}
