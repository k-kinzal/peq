<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;

/**
 * @internal
 */
final class InClassMethodCollectorTest extends CollectorTestCase
{
    public function testCollectMethodBodyDependencies(): void
    {
        $this->assertCollected(
            new InClassMethodCollector(),
            __DIR__.'/Fixture/method_body.php',
            $this->assertCollectedMethodBody(...)
        );
    }

    /**
     * @param array<Edge|Node> $items
     */
    private function assertCollectedMethodBody(array $items): void
    {
        $edgeKinds = [];
        foreach ($items as $item) {
            if ($item instanceof Edge) {
                $edgeKinds[] = $item->kind();
            }
        }

        self::assertContains(EdgeKind::Instantiation, $edgeKinds, 'Instantiation edge missing');
        self::assertContains(EdgeKind::StaticCall, $edgeKinds, 'StaticCall edge missing');
        self::assertContains(EdgeKind::ConstFetch, $edgeKinds, 'ConstFetch edge missing');
    }
}
