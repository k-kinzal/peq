<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Which format a report is written in.
 *
 * peq answers one question — what does this symbol reach, or what reaches it —
 * and the answer is read by very different readers. A person scanning a terminal
 * wants the shape of the graph at a glance; a program wants fields it can address;
 * a picture wants nodes and arrows; a review wants a flat list it can sort and
 * grep. Those are four ways of writing down the same walk, not four analyses, and
 * naming them as a closed type is what lets the reporter factory answer the choice
 * with one arm per format rather than a branch inside a command.
 */
enum OutputFormat: string
{
    /** Draws the walk as an indented tree, the way `tree` draws a directory */
    case Tree = 'tree';

    /** Writes the walk as a JSON document, for a program or an agent to read */
    case Json = 'json';

    /** Writes the walk as a Graphviz digraph, for rendering as a picture */
    case Dot = 'dot';

    /** Writes the walk as a table, one row per symbol it reached */
    case Table = 'table';

    /** Draws the graph itself in the terminal: every symbol once, numbered, with arrows between them */
    case Graph = 'graph';

    /**
     * Writes out the formats a report can be asked for, as they are written.
     *
     * The command line documents its own choices from this rather than from a
     * sentence someone has to remember to update, so a format that exists is a
     * format the help text offers.
     *
     * @example The choices are written the way the command line spells them
     *     \App\Config\OutputFormat::spell() // => 'tree|json|dot|table|graph'
     *
     * @return string The formats, separated by the character that separates them on the command line
     */
    public static function spell(): string
    {
        return implode('|', array_map(static fn (self $format): string => $format->value, self::cases()));
    }
}
