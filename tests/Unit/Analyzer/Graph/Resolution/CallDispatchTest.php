<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\Resolution\CallDispatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallDispatch::class)]
#[Medium]
final class CallDispatchTest extends TestCase
{
    public function testEnrichOnlyIncludesImplementationsSatisfyingTheReceiverConstraintAreCandidates(): void
    {
        $graph = (new \App\Analyzer\NativeAnalyzer\NativeAnalyzer())->analyze(dirname(__DIR__, 4).'/Fixture/Source/Dip.php');

        CallDispatch::enrich($graph);
        $edges = array_values(array_filter($graph->forwardEdges(), static fn ($edge): bool => $edge->kind() === \App\Analyzer\Graph\EdgeKind::PossibleCall && $edge->from()->toString() === 'Tests\Fixture\Source\Dip\Controller::action'));
        $targets = array_map(static fn ($edge): string => $edge->to()->toString(), $edges);

        self::assertSame(['Tests\Fixture\Source\Dip\BaseService::execute', 'Tests\Fixture\Source\Dip\BaseService::execute'], $targets);
    }
}
