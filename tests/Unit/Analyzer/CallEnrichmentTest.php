<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CallEnrichment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallEnrichment::class)]
#[Medium]
final class CallEnrichmentTest extends TestCase
{
    public function testOfDoesNotDuplicateSourceOccurrences(): void
    {
        $graph = (new \App\Analyzer\NativeAnalyzer\NativeAnalyzer())->analyze(dirname(__DIR__, 2).'/Fixture/Source/Dip.php');
        $before = count($graph->forwardEdges());

        $enriched = CallEnrichment::of($graph, 80300);

        self::assertSame($graph, $enriched);
        self::assertSame($before, count($enriched->forwardEdges()));
        self::assertNotNull($enriched->nodeNamed('PDO::query'));
    }
}
