<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\Reporter;
use App\Reporter\Traversal;
use App\Reporter\Traversal\DependencyTraversal;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports analysis results as a text-based tree structure.
 *
 * This reporter visualizes the dependency graph as a hierarchical tree, similar to the
 * Unix `tree` command. It uses a traversal strategy to visit nodes and a line renderer
 * to format the output. It supports depth limiting and handles cyclic dependencies.
 */
final class TreeReporter implements Reporter
{
    public function __construct(
        private readonly TreeReporterOptions $options,
        private readonly Traversal $traversal = new DependencyTraversal(),
        private readonly LineRenderer $lineRenderer = new LineRenderer(),
    ) {}

    /**
     * {@inheritdoc}
     */
    public function report(Graph $graph, NodeId $symbol, OutputInterface $output): void
    {
        $continuationFlags = [];
        $parentStack = [];
        $pathStack = [];

        $this->traversal->traverse(
            $graph,
            $symbol,
            function (
                Node $node,
                int $depth
            ) use (
                $graph,
                $output,
                &$continuationFlags,
                &$parentStack,
                &$pathStack
            ) {
                if ($this->options->level !== null && $depth > $this->options->level) {
                    return false;
                }

                while (count($pathStack) > $depth) {
                    array_pop($pathStack);
                }

                $nodeKey = $node->id()->toString();

                $isRecursive = in_array($nodeKey, $pathStack, true);

                $isLastChild = true;
                if ($depth > 0 && isset($parentStack[$depth - 1])) {
                    $parentNode = $parentStack[$depth - 1];
                    $siblings = [];
                    $edges = $graph->edges($parentNode->id());
                    foreach ($edges as $edge) {
                        if (
                            $edge->kind() !== EdgeKind::UsedBy
                            && $edge->kind() !== EdgeKind::DeclaredIn
                        ) {
                            $siblings[] = $edge->to()->toString();
                        }
                    }

                    if ($siblings !== []) {
                        $lastSiblingId = end($siblings);
                        $isLastChild = ($nodeKey === $lastSiblingId);
                    }
                }

                $line = $this->lineRenderer->render($node, $depth, $continuationFlags, $isLastChild, $isRecursive);
                $output->writeln($line);

                $continuationFlags[$depth] = !$isLastChild;

                $parentStack[$depth] = $node;

                if (!$isRecursive) {
                    $pathStack[] = $nodeKey;
                }

                if (
                    $isRecursive
                    || $node->kind() === NodeKind::Builtin
                    || $node->kind() === NodeKind::Unknown
                ) {
                    return false;
                }

                return true;
            }
        );
    }
}
