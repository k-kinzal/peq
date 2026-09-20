<?php

declare(strict_types=1);

namespace App\Reporter\TableReporter;

use App\Analyzer\Graph\Node;
use App\Reporter\Expansion;

/**
 * The rows one table report is accumulating as the walk goes on.
 *
 * A table says the same things a tree line says, in columns instead of in prefixes
 * and suffixes. Two of them survive the change of medium unaltered: how deep a symbol
 * sits, and what it is called. Two are things the tree can only hint at — the kind of
 * symbol, which the tree writes as a parenthesis for the two kinds it has a word for,
 * and where the symbol is written, which the tree has no room for at all.
 *
 * What does not survive is the shape. A table is a list, and a list is what it is for:
 * something to sort, grep, paste into a review and count the length of.
 *
 * @visibility namespace
 */
final class TableCursor
{
    /**
     * Written in a cell the walk has nothing to put in.
     */
    private const ABSENT = '-';

    /**
     * @var list<list<string>> One row per node the walk reached, in the order it reached them
     */
    private array $rows = [];

    /**
     * How far this report has already expanded the graph.
     */
    private readonly Expansion $expansion;

    /**
     * @param null|int $level Deepest level to report, or null for the whole graph
     */
    public function __construct(?int $level = null)
    {
        $this->expansion = new Expansion($level);
    }

    /**
     * Records one node as a row and reports whether the walk should continue below it.
     *
     * A branch the walk cuts is named in the symbol cell, the way the tree names it
     * at the end of a line. Without that, a row with nothing under it would read as a
     * symbol that depends on nothing, which is a different statement.
     *
     * @param Node $node  The node the traversal reached
     * @param int  $depth How far below the root symbol it sits
     *
     * @return bool True when the traversal should descend into this node
     */
    public function visit(Node $node, int $depth): bool
    {
        $continuation = $this->expansion->reach($node, $depth);
        if (!$continuation->reported()) {
            return false;
        }

        $marker = $continuation->marker();
        $meta = $node->meta();

        $this->rows[] = [
            (string) $depth,
            $marker === null ? $node->id()->toString() : sprintf('%s (%s)', $node->id()->toString(), $marker),
            $node->kind()->value,
            $meta === null ? self::ABSENT : sprintf('%s:%d', $meta->path, $meta->line),
        ];

        return $continuation->descends();
    }

    /**
     * Returns the rows written for the nodes the walk reached.
     *
     * @return list<list<string>> The rows, in the order the walk reached them
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
