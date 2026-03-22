<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class InClassMethodCollectorUsageTest extends CollectorTestCase
{
    #[Test]
    public function testUsageProcessorsProduceCorrectEdges(): void
    {
        $this->assertCollected(
            new InClassMethodCollector(),
            __DIR__.'/Fixture/usage_processors.php',
            $this->assertUsageEdges(...)
        );
    }

    /**
     * @param array<Edge|Node> $items
     */
    private function assertUsageEdges(array $items): void
    {
        self::assertNotEmpty($items);

        $hasEdge = function (string $sourceSuffix, EdgeKind $kind, ?string $targetSuffix = null) use ($items): bool {
            foreach ($items as $item) {
                if ($item instanceof Edge
                    && str_ends_with($item->from()->toString(), $sourceSuffix)
                    && $item->kind() === $kind
                    && ($targetSuffix === null || str_ends_with($item->to()->toString(), $targetSuffix))) {
                    return true;
                }
            }

            return false;
        };

        // FunctionCallProcessor
        self::assertTrue(
            $hasEdge('UsageProcessorFixture::testMethod', EdgeKind::FunctionCall, 'usage_target_func'),
            'FunctionCall edge missing for usage_target_func()'
        );

        // MethodCallProcessor
        self::assertTrue(
            $hasEdge('UsageProcessorFixture::testMethod', EdgeKind::MethodCall, 'UsageProcessorFixture::helperMethod'),
            'MethodCall edge missing for $this->helperMethod()'
        );

        // PropertyAccessProcessor
        self::assertTrue(
            $hasEdge('UsageProcessorFixture::testMethod', EdgeKind::PropertyAccess, 'UsageProcessorFixture::myProp'),
            'PropertyAccess edge missing for $this->myProp'
        );

        // StaticPropertyAccessProcessor
        self::assertTrue(
            $hasEdge('UsageProcessorFixture::testMethod', EdgeKind::StaticPropertyAccess, 'UsageDep::staticCount'),
            'StaticPropertyAccess edge missing for UsageDep::$staticCount'
        );

        // Non-detection: $obj->method() should NOT produce MethodCall to UsageDep::depMethod
        self::assertFalse(
            $hasEdge('UsageProcessorFixture::testMethod', EdgeKind::MethodCall, 'UsageDep::depMethod'),
            'MethodCall edge should NOT exist for $dep->depMethod() ($obj-> not supported)'
        );

        // Non-detection: $obj->prop should NOT produce PropertyAccess to UsageDep::instanceProp
        self::assertFalse(
            $hasEdge('UsageProcessorFixture::testMethod', EdgeKind::PropertyAccess, 'UsageDep::instanceProp'),
            'PropertyAccess edge should NOT exist for $dep->instanceProp ($obj-> not supported)'
        );
    }
}
