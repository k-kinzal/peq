<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;

/**
 * @internal
 */
final class DependencyCollectorTest extends CollectorTestCase
{
    public function testCollectComprehensive(): void
    {
        $this->assertCollected(
            new DependencyCollector(),
            __DIR__.'/Fixture/comprehensive.php',
            $this->assertCollectedComprehensive(...)
        );
    }

    /**
     * @param array<Edge|Node> $items
     */
    private function assertCollectedComprehensive(array $items): void
    {
        self::assertNotEmpty($items);

        // Helper to find node by ID suffix
        $findNode = function (string $suffix) use ($items) {
            foreach ($items as $item) {
                if ($item instanceof Node && str_ends_with($item->id()->toString(), $suffix)) {
                    return $item;
                }
            }

            return null;
        };

        // Assert Nodes
        self::assertNotNull($findNode('MyAttribute'), 'Attribute class node missing');
        self::assertNotNull($findNode('MyInterface'), 'Interface node missing');
        self::assertNotNull($findNode('MyTrait'), 'Trait node missing');
        self::assertNotNull($findNode('MyEnum'), 'Enum node missing');
        self::assertNotNull($findNode('MyEnum::A'), 'EnumCase node missing');
        self::assertNotNull($findNode('ComprehensiveClass'), 'Class node missing');
        self::assertNotNull($findNode('ComprehensiveClass::MY_CONST'), 'Constant node missing');
        self::assertNotNull($findNode('ComprehensiveClass::myProp'), 'Property node missing');
        self::assertNotNull($findNode('ComprehensiveClass::promotedProp'), 'Promoted Property node missing');
        self::assertNotNull($findNode('ComprehensiveClass::myMethod'), 'Method node missing');

        // Helper to check edge existence
        $hasEdge = function (string $sourceSuffix, EdgeKind $kind) use ($items) {
            foreach ($items as $item) {
                if ($item instanceof Edge
                    && str_ends_with($item->from()->toString(), $sourceSuffix)
                    && $item->kind() === $kind) {
                    return true;
                }
            }

            return false;
        };

        // Assert Edges
        self::assertTrue($hasEdge('ComprehensiveClass', EdgeKind::Attribute), 'Attribute edge missing on class');
        self::assertTrue($hasEdge('ComprehensiveClass', EdgeKind::DeclarationImplements), 'Implements edge missing');
        self::assertTrue($hasEdge('ComprehensiveClass', EdgeKind::DeclarationTraitUse), 'Trait use edge missing');

        self::assertTrue($hasEdge('ComprehensiveClass::MY_CONST', EdgeKind::Attribute), 'Attribute edge missing on constant');
        self::assertTrue($hasEdge('ComprehensiveClass::myProp', EdgeKind::Attribute), 'Attribute edge missing on property');
        self::assertTrue($hasEdge('ComprehensiveClass::promotedProp', EdgeKind::Attribute), 'Attribute edge missing on promoted property');
        self::assertTrue($hasEdge('ComprehensiveClass::myMethod', EdgeKind::Attribute), 'Attribute edge missing on method');
    }
}
