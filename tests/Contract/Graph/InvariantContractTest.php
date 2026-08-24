<?php

declare(strict_types=1);

namespace Tests\Contract\Graph;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Graph;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * @internal
 */#[CoversClass(Graph::class)]
#[Large]
final class InvariantContractTest extends TestCase
{
    use TestTrait;

    /**
     * Checks that bidirectional edge contract.
     */
    #[Test]
    public function testBidirectionalEdgeContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                GraphInvariants::assertBidirectional($graph);
            })
        ;
    }

    /**
     * Checks that edge endpoint existence contract.
     */
    #[Test]
    public function testEdgeEndpointExistenceContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                GraphInvariants::assertEndpointsExist($graph);
            })
        ;
    }

    /**
     * Checks that node uniqueness contract.
     */
    #[Test]
    public function testNodeUniquenessContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                GraphInvariants::assertNodeUniqueness($graph);
            })
        ;
    }

    /**
     * Checks that merge preserves contracts.
     */
    #[Test]
    public function testMergePreservesContracts(): void
    {
        $this->forAll(Generator\choose(1, 10000), Generator\choose(1, 10000))
            ->then(function (int $seed1, int $seed2): void {
                $g1 = (new DebugAnalyzer(seed: $seed1, depth: 2))->analyze('/fake');
                $g2 = (new DebugAnalyzer(seed: $seed2, depth: 2))->analyze('/fake');
                $merged = $g1->merge($g2);

                GraphInvariants::assertAllNodesPreserved($g1, $merged);
                GraphInvariants::assertAllNodesPreserved($g2, $merged);
                GraphInvariants::assertBidirectional($merged);
                GraphInvariants::assertNoEdgeDuplicates($merged);
            })
        ;
    }

    /**
     * Checks that no edge duplicates contract.
     */
    #[Test]
    public function testNoEdgeDuplicatesContract(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $graph = (new DebugAnalyzer(seed: $seed, depth: 3))->analyze('/fake');
                GraphInvariants::assertNoEdgeDuplicates($graph);
            })
        ;
    }
}
