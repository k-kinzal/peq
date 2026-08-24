<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;

/**
 * Renders a single line of the dependency tree.
 *
 * This class is responsible for formatting the tree structure characters (│, ├──, └──)
 * and the node information (ID, recursion status, builtin status) for a single node
 * in the dependency tree output.
 *
 * @visibility namespace
 */
final class LineRenderer
{
    /**
     * Formats one node as a line of the tree.
     *
     * The root sits at depth zero and carries no prefix. Below it, one indent per
     * level is drawn, continuing the branch (`│`) where a sibling still follows at
     * that level, and the node is joined with `└──` when it is the last of its
     * siblings and `├──` otherwise. A suffix names why a branch stops: `(recursive)`
     * for a cycle, `(*)` for a node expanded elsewhere in the same report,
     * `(builtin)` for a PHP builtin and `(unresolved)` for a symbol outside the
     * analyzed sources.
     *
     * Examples of the resulting lines:
     * - `MyClass` — the root
     * - `├── MyClass::method1` — a child with a sibling below it
     * - `└── MyClass::method2` — the last child
     * - `│   ├── Dependency1` — a grandchild under a branch that continues
     * - `│   └── MyClass::method1 (recursive)` — a cycle back onto the path
     * - `├── array_map (builtin)` — a PHP builtin
     * - `├── UnknownClass (unresolved)` — a symbol outside the analyzed sources
     * - `│   └── SharedDep (*)` — already expanded elsewhere in this report
     *
     * @param Node             $node              The node to format
     * @param int              $depth             How far below the root symbol it sits
     * @param array<int, bool> $continuationFlags Whether the branch at each level continues below
     * @param bool             $isLastChild       Whether no further sibling follows it
     * @param bool             $isRecursive       Whether it closes a cycle on the current path
     * @param bool             $isDuplicate       Whether it was already expanded elsewhere
     *
     * @return string The formatted line, without a trailing newline
     */
    public function render(
        Node $node,
        int $depth,
        array $continuationFlags,
        bool $isLastChild,
        bool $isRecursive,
        bool $isDuplicate = false,
    ): string {
        if ($depth === 0) {
            return $node->id()->toString();
        }

        $line = '';

        for ($i = 1; $i < $depth; ++$i) {
            $line .= ($continuationFlags[$i] ?? false) ? '│   ' : '    ';
        }

        $line .= $isLastChild ? '└── ' : '├── ';

        $line .= $node->id()->toString();

        if ($isRecursive) {
            $line .= ' (recursive)';
        } elseif ($isDuplicate) {
            $line .= ' (*)';
        } elseif ($node->kind() === NodeKind::Builtin) {
            $line .= ' (builtin)';
        } elseif ($node->kind() === NodeKind::Unknown) {
            $line .= ' (unresolved)';
        }

        return $line;
    }
}
