<?php

declare(strict_types=1);

namespace App\Reporter\DotReporter;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\CallOccurrences;

/**
 * Renders a single statement of a Graphviz digraph.
 *
 * The DOT language is small: a graph is an opening line, a run of statements naming
 * nodes and arrows, and a closing brace. What it is not is forgiving — a PHP symbol
 * is full of backslashes, and a backslash inside a DOT string escapes the character
 * after it. Every name that reaches the file therefore passes through one place,
 * which is this one, so no statement can be written that forgets to quote.
 *
 * @visibility namespace
 */
final class StatementRenderer
{
    /**
     * How far a statement inside the graph body is indented.
     */
    private const INDENT = '    ';

    /**
     * Opens the digraph, naming it after the symbol the report is rooted at.
     *
     * The graph is laid out left to right, because a dependency chain is read as a
     * sequence and a tall narrow picture is harder to follow than a wide one.
     *
     * @param string $symbol The symbol the walk starts at
     *
     * @return list<string> The lines that open the graph
     */
    public function open(string $symbol): array
    {
        return [
            sprintf('digraph %s {', self::quote($symbol)),
            self::INDENT.'rankdir="LR";',
        ];
    }

    /**
     * Closes the digraph.
     *
     * @return string The line that closes the graph
     */
    public function close(): string
    {
        return '}';
    }

    /**
     * Declares one node, shaped by the kind of symbol it stands for.
     *
     * @param Node $node The node to declare
     *
     * @return string The node statement
     */
    public function node(Node $node): string
    {
        return sprintf(
            '%s%s [shape="%s"];',
            self::INDENT,
            self::quote($node->id()->toString()),
            self::shape($node->kind()),
        );
    }

    /**
     * Draws one relation, labelled with the kind of relation it is.
     *
     * @param Edge $edge The relation to draw
     *
     * @return string The edge statement
     */
    public function edge(Edge $edge): string
    {
        return sprintf(
            '%s%s -> %s [label=%s];',
            self::INDENT,
            self::quote($edge->from()->toString()),
            self::quote($edge->to()->toString()),
            self::quote(CallOccurrences::label($edge)),
        );
    }

    /**
     * Writes a name as a DOT string.
     *
     * A quoted DOT string ends at the first unescaped quote and treats a backslash as
     * the start of an escape, so both have to be escaped for a name to survive. A PHP
     * namespace separator is a backslash, which makes this the rule every symbol in
     * the output depends on rather than an edge case.
     *
     * @param string $name The name to write
     *
     * @example A namespaced symbol keeps every separator it was written with
     *     \App\Reporter\DotReporter\StatementRenderer::quote('App\\Domain\\Invoice') // => '"App\\\\Domain\\\\Invoice"'
     *
     * @return string The name, quoted and escaped
     */
    public static function quote(string $name): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $name).'"';
    }

    /**
     * Chooses the shape a kind of symbol is drawn with.
     *
     * The shapes group the way the language does: the things a file declares are
     * boxes of one sort or another, the things that run are ellipses, the things that
     * hold a value are notes, and the two kinds that stand for something outside the
     * analyzed sources are drawn as what they are — a bare name and a stop sign.
     *
     * @param NodeKind $kind The kind of symbol the node stands for
     *
     * @example A class is drawn as a box
     *     \App\Reporter\DotReporter\StatementRenderer::shape(\App\Analyzer\Graph\NodeKind::Klass) // => 'box'
     * @example A symbol analysis could not resolve is drawn as a stop sign
     *     \App\Reporter\DotReporter\StatementRenderer::shape(\App\Analyzer\Graph\NodeKind::Unknown) // => 'octagon'
     *
     * @return string The Graphviz shape name
     */
    public static function shape(NodeKind $kind): string
    {
        return match ($kind) {
            NodeKind::Klass => 'box',
            NodeKind::Interface => 'component',
            NodeKind::Trait => 'folder',
            NodeKind::Enum => 'tab',

            NodeKind::Method,
            NodeKind::Function,
            NodeKind::Closure => 'ellipse',

            NodeKind::Property => 'parallelogram',

            NodeKind::Constant,
            NodeKind::EnumCase => 'note',

            NodeKind::Builtin => 'plaintext',
            NodeKind::Unknown => 'octagon',
        };
    }
}
