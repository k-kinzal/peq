<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\Traversal;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The position of one tree report as it is being written.
 *
 * A traversal hands nodes over one at a time in depth-first order and says nothing
 * about layout. Turning that stream into a tree needs memory: which branches are
 * still open above the current line, which node was the parent at each depth, which
 * identifiers are on the current path, and which ones were already expanded
 * somewhere else. That memory belongs to a single report, so it lives here rather
 * than in the reporter, and every decision it drives is a named method rather than
 * a captured variable.
 *
 * @visibility namespace
 */
final class TreeCursor
{
    /**
     * @var array<int, bool> Whether the branch at each depth continues below the current line
     */
    private array $continuations = [];

    /**
     * @var array<int, Node> The node that was visited last at each depth
     */
    private array $parents = [];

    /**
     * @var list<string> The identifiers on the path from the root to the current line
     */
    private array $path = [];

    /**
     * @var array<string, true> The identifiers already expanded somewhere in this report
     */
    private array $expanded = [];

    /**
     * @param Graph           $graph        The graph being reported on
     * @param Traversal       $traversal    The traversal whose edges decide sibling order
     * @param LineRenderer    $lineRenderer The format of a single line
     * @param OutputInterface $output       Where lines are written
     * @param null|int        $level        Deepest level to print, or null for the whole tree
     */
    public function __construct(
        private readonly Graph $graph,
        private readonly Traversal $traversal,
        private readonly LineRenderer $lineRenderer,
        private readonly OutputInterface $output,
        private readonly ?int $level = null,
    ) {}

    /**
     * Writes one node and reports whether the tree should continue below it.
     *
     * A node is not expanded twice in one report: the second time it appears it is
     * marked and left closed, which keeps a shared dependency from multiplying the
     * output. A node on the current path is a cycle and is marked as such. Builtin
     * and unresolved nodes have nothing below them to print.
     *
     * @param Node $node  The node the traversal reached
     * @param int  $depth How far below the root symbol it sits
     *
     * @return bool True when the traversal should descend into this node
     */
    public function visit(Node $node, int $depth): bool
    {
        if ($this->level !== null && $depth > $this->level) {
            return false;
        }

        while (count($this->path) > $depth) {
            array_pop($this->path);
        }

        $nodeKey = $node->id()->toString();
        $isRecursive = in_array($nodeKey, $this->path, true);
        $isDuplicate = !$isRecursive && isset($this->expanded[$nodeKey]);
        $isLastChild = $this->isLastChild($node, $depth);

        $this->output->writeln($this->lineRenderer->render(
            node: $node,
            depth: $depth,
            continuationFlags: $this->continuations,
            isLastChild: $isLastChild,
            isRecursive: $isRecursive,
            isDuplicate: $isDuplicate,
        ));

        $this->continuations[$depth] = !$isLastChild;
        $this->parents[$depth] = $node;
        if (!$isRecursive) {
            $this->path[] = $nodeKey;
        }

        if ($isRecursive || $isDuplicate || $this->isLeafKind($node->kind())) {
            return false;
        }

        $this->expanded[$nodeKey] = true;

        return true;
    }

    /**
     * Reports whether a node is the last of the children drawn under its parent.
     *
     * The answer decides between a `└──` and a `├──` prefix, and it is asked of the
     * graph rather than of the traversal, because the traversal has already moved on
     * by the time the line is written.
     *
     * @param Node $node  The node being written
     * @param int  $depth How far below the root symbol it sits
     *
     * @return bool True when no further sibling follows it
     */
    public function isLastChild(Node $node, int $depth): bool
    {
        $parent = $depth > 0 ? ($this->parents[$depth - 1] ?? null) : null;
        if ($parent === null) {
            return true;
        }

        $siblings = [];
        foreach ($this->graph->edges($parent->id()) as $edge) {
            if ($edge->kind()->direction() === $this->traversal->direction()) {
                $siblings[] = $edge->to()->toString();
            }
        }

        $siblings = array_unique($siblings);
        if ($siblings === []) {
            return true;
        }

        return $node->id()->toString() === end($siblings);
    }

    /**
     * Reports whether a kind of node can have anything printed below it.
     *
     * A builtin type and an unresolved reference are both outside the analyzed
     * sources, so the graph holds no relations for them to descend into.
     *
     * @param NodeKind $kind The kind of the node being written
     *
     * @return bool True when the node is a leaf of the printed tree
     */
    public function isLeafKind(NodeKind $kind): bool
    {
        return match ($kind) {
            NodeKind::Builtin,
            NodeKind::Unknown => true,

            NodeKind::Klass,
            NodeKind::Constant,
            NodeKind::EnumCase,
            NodeKind::Enum,
            NodeKind::Function,
            NodeKind::Interface,
            NodeKind::Method,
            NodeKind::Property,
            NodeKind::Trait => false,
        };
    }
}
