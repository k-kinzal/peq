<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use RuntimeException;

/**
 * A collector that gives up on the first node it is shown.
 *
 * Tests of the analyzer need a failure PHPStan cannot recover from, so they can
 * check that the analysis reports the failure instead of handing back a graph
 * that quietly lacks the file.
 *
 * @implements Collector<Node, null>
 */
final class FailingCollector implements Collector
{
    /**
     * {@inheritdoc}
     */
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * {@inheritdoc}
     */
    public function processNode(Node $node, Scope $scope): mixed
    {
        throw new RuntimeException('the collector gave up on a '.$node->getType());
    }
}
