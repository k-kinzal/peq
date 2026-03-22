<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class InvariantContractTest extends TestCase
{
    use GraphInvariantAssertions;
    use TestTrait;

    #[Test]
    public function testBidirectionalEdgeContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                self::assertBidirectional($graph);
            })
        ;
    }

    #[Test]
    public function testEdgeEndpointExistenceContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                self::assertEndpointsExist($graph);
            })
        ;
    }

    #[Test]
    public function testNodeUniquenessContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                self::assertNodeUniqueness($graph);
            })
        ;
    }

    #[Test]
    public function testMergePreservesContracts(): void
    {
        $this->forAll(Generator\choose(1, 10000), Generator\choose(1, 10000))
            ->then(function (int $seed1, int $seed2): void {
                $g1 = (new DebugAnalyzer(seed: $seed1, depth: 2))->analyze('/fake');
                $g2 = (new DebugAnalyzer(seed: $seed2, depth: 2))->analyze('/fake');
                $merged = $g1->merge($g2);

                self::assertAllNodesPreserved($g1, $merged);
                self::assertAllNodesPreserved($g2, $merged);
                self::assertBidirectional($merged);
                self::assertNoEdgeDuplicates($merged);
            })
        ;
    }

    #[Test]
    public function testNoEdgeDuplicatesContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                self::assertNoEdgeDuplicates($graph);
            })
        ;
    }
}
