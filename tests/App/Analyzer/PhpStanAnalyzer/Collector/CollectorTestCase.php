<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use PhpParser\Node as PhpParserNode;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Collectors\Collector;
use PHPStan\Testing\PHPStanTestCase;

abstract class CollectorTestCase extends PHPStanTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    /**
     * @param Collector<PhpParserNode, mixed>  $collector
     * @param callable(array<Edge|Node>): void $assertionCallback
     */
    protected function assertCollected(Collector $collector, string $file, callable $assertionCallback): void
    {
        $nodeScopeResolver = self::getContainer()->getByType(NodeScopeResolver::class);
        $items = [];

        $nodeScopeResolver->processNodes(
            self::getParser()->parseFile($file),
            self::getContainer()->getByType(ScopeFactory::class)->create(ScopeContext::create($file)),
            function (PhpParserNode $node, Scope $scope) use ($collector, &$items): void {
                if ($node instanceof ($collector->getNodeType())) {
                    $result = $collector->processNode($node, $scope);
                    if (is_array($result)) {
                        /** @var array<Edge|Node> $result */
                        $items = array_merge($items, $result);
                    }
                }
            }
        );

        /** @var array<Edge|Node> $items */
        $assertionCallback($items);
    }
}
