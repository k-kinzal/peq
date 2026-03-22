<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Processor\InClassMethodNodeProcessor;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassMethodNode;

/**
 * Collects dependency information from InClassMethodNode.
 *
 * @implements Collector<InClassMethodNode, array<Node|Edge>>
 */
final class InClassMethodCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * @return null|array<Edge|Node>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): ?array
    {
        if (!$node instanceof InClassMethodNode) {
            return null;
        }

        $items = InClassMethodNodeProcessor::process($node, $scope);

        if ($items === []) {
            return null;
        }

        return $items;
    }
}
