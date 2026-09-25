<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;

/**
 * How far one report has expanded the graph, and what it does with the next node.
 *
 * A dependency graph is not a tree. It has cycles, and it has symbols that several
 * branches reach; a report that followed it naively would either never finish or
 * would repeat the same subtree until the terminal scrolled. Deciding where to stop
 * therefore needs memory: which identifiers are open on the path above the node being
 * reported, and which ones were already expanded somewhere else in the same report.
 *
 * That memory belongs to a single report, so it is created for one and thrown away
 * with it, and it lives here rather than in any one format. Every reporter asks the
 * same question of it and is given the same answer, which is what makes "the tree and
 * the JSON describe the same walk" a property of the code rather than a coincidence.
 */
final class Expansion
{
    /**
     * @var list<string> The identifiers on the path from the root to the node being reported
     */
    private array $path = [];

    /**
     * @var array<string, true> The identifiers already expanded somewhere in this report
     */
    private array $expanded = [];

    /**
     * @param null|int $level Deepest level below the root to report, or null for the whole graph
     */
    public function __construct(
        private readonly ?int $level = null,
    ) {
        assert($level === null || $level > 0, 'A reported level bound must be a positive number of levels');
    }

    /**
     * Records that the walk reached a node, and says what the report does with it.
     *
     * The order the reasons are weighed in is the order they override each other. A
     * node past the level bound is not reported at all, so nothing else about it
     * matters. A node on the current path closes a cycle whether or not it was
     * expanded elsewhere, because the branch has to end either way and a cycle is the
     * more useful thing to say. Only a node that is none of those is expanded, and
     * only then is it remembered as expanded — so the one place a symbol is opened
     * out is the first branch to reach it.
     *
     * @param Node $node  The node the traversal reached
     * @param int  $depth How far below the root symbol it sits
     *
     * @return Continuation What the report does with the node
     */
    public function reach(Node $node, int $depth): Continuation
    {
        if ($this->level !== null && $depth > $this->level) {
            return Continuation::Beyond;
        }

        while (count($this->path) > $depth) {
            array_pop($this->path);
        }

        $key = $node->id()->toString();
        if (in_array($key, $this->path, true)) {
            return Continuation::Cycle;
        }

        $this->path[] = $key;

        if (isset($this->expanded[$key])) {
            return Continuation::Repeat;
        }
        if (self::isLeafKind($node->kind())) {
            return Continuation::Leaf;
        }

        $this->expanded[$key] = true;

        return Continuation::Descends;
    }

    /**
     * Reports whether a kind of node can have anything below it.
     *
     * A builtin type and an unresolved reference are both outside the analyzed
     * sources, so the graph holds no relations to descend into. Every other kind is
     * something a codebase declares, and a declaration can relate to others.
     *
     * @param NodeKind $kind The kind of the node reached
     *
     * @example A builtin type is the end of its branch
     *     \App\Reporter\Expansion::isLeafKind(\App\Analyzer\Graph\NodeKind::Builtin) // => true
     * @example A class is not
     *     \App\Reporter\Expansion::isLeafKind(\App\Analyzer\Graph\NodeKind::Klass) // => false
     *
     * @return bool True when the node is a leaf of every report
     */
    public static function isLeafKind(NodeKind $kind): bool
    {
        return match ($kind) {
            NodeKind::Builtin,
            NodeKind::Unknown => true,

            NodeKind::Klass,
            NodeKind::Constant,
            NodeKind::EnumCase,
            NodeKind::Enum,
            NodeKind::Function,
            NodeKind::Closure,
            NodeKind::Interface,
            NodeKind::Method,
            NodeKind::Property,
            NodeKind::Trait => false,
        };
    }
}
