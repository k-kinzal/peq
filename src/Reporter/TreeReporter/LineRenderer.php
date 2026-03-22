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
 */
final class LineRenderer
{
    /**
     * @param bool[] $continuationFlags
     *
     * @return string
     *
     * Example outputs:
     * - Root node (depth=0): MyClass
     * - First level, not last child: ├── MyClass::method1
     * - First level, last child: └── MyClass::method2
     * - Second level with continuation, not last child: │   ├── Dependency1
     * - Second level with continuation, last child: │   └── Dependency2
     * - Second level without continuation, not last child:     ├── OtherDep1
     * - Second level without continuation, last child:     └── OtherDep2
     * - Third level with multiple continuations: │   │   ├── DeepDep
     * - Third level with partial continuations: │       └── DeepDep
     * - Deep nesting (depth >= 3): │   │   │   └── VeryDeepDep
     * - Builtin function reference: ├── array_map (builtin)
     * - Recursive reference detected: │   └── MyClass::method1 (recursive)
     * - Unresolved reference: ├── UnknownClass (unresolved)
     * - Builtin at deep level: │   │   └── strlen (builtin)
     * - Mixed recursive and continuation: │   ├── Helper::process (recursive)
     */
    public function render(
        Node $node,
        int $depth,
        array $continuationFlags,
        bool $isLastChild,
        bool $isRecursive
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
        } elseif ($node->kind() === NodeKind::Builtin) {
            $line .= ' (builtin)';
        } elseif ($node->kind() === NodeKind::Unknown) {
            $line .= ' (unresolved)';
        }

        return $line;
    }
}
