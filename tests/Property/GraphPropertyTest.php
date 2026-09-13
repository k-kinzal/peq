<?php

declare(strict_types=1);

namespace Tests\Property;

use App\Analyzer\Graph\Graph;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Graph\GeneratedRelations;
use Tests\Fixture\Graph\GraphInvariants;

/**
 * The graph model, checked over arbitrary relations rather than chosen ones.
 *
 * Every property here is one of the graph's own promises. What asking them this way
 * adds is the counterexample: Eris shrinks a failing list of relations down to the
 * shortest one that still breaks the promise, which is the difference between "a
 * generated graph was wrong somewhere" and "these two relations are wrong".
 *
 * @group pbt
 *
 * @internal
 */
#[CoversClass(Graph::class)]
#[Group('pbt')]
#[Large]
final class GraphPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Checks that every relation the graph records is readable from both of its ends.
     */
    public function testEveryRelationIsReadableFromBothOfItsEnds(): void
    {
        $this->forAll(GeneratedRelations::specifications())
            ->then(static function (array $relations): void {
                GraphInvariants::assertBidirectional(GeneratedRelations::graphOf($relations));
            })
        ;
    }

    /**
     * Checks that no relation points at a symbol the graph does not hold.
     */
    public function testNoRelationPointsAtASymbolTheGraphDoesNotHold(): void
    {
        $this->forAll(GeneratedRelations::specifications())
            ->then(static function (array $relations): void {
                GraphInvariants::assertEndpointsExist(GeneratedRelations::graphOf($relations));
            })
        ;
    }

    /**
     * Checks that an identifier never names more than one symbol.
     */
    public function testAnIdentifierNamesAtMostOneSymbol(): void
    {
        $this->forAll(GeneratedRelations::specifications())
            ->then(static function (array $relations): void {
                GraphInvariants::assertNodeUniqueness(GeneratedRelations::graphOf($relations));
            })
        ;
    }

    /**
     * Checks that the same relation is never recorded twice in one direction.
     */
    public function testTheSameRelationIsRecordedAtMostOncePerDirection(): void
    {
        $this->forAll(GeneratedRelations::specifications())
            ->then(static function (array $relations): void {
                GraphInvariants::assertNoEdgeDuplicates(GeneratedRelations::graphOf($relations));
            })
        ;
    }

    /**
     * Checks that inverting any relation twice yields the relation it started from.
     */
    public function testInvertingAnyRelationTwiceYieldsTheRelationItStartedFrom(): void
    {
        $this->forAll(GeneratedRelations::specifications())
            ->then(static function (array $relations): void {
                GraphInvariants::assertInversionRoundTrips(GeneratedRelations::graphOf($relations));
            })
        ;
    }

    /**
     * Checks that merging two graphs keeps every symbol both of them hold.
     */
    public function testMergeKeepsEverySymbolOfBothGraphs(): void
    {
        $this->forAll(GeneratedRelations::specifications(), GeneratedRelations::specifications())
            ->then(static function (array $left, array $right): void {
                $first = GeneratedRelations::graphOf($left);
                $second = GeneratedRelations::graphOf($right);
                $merged = $first->merge($second);

                GraphInvariants::assertAllNodesPreserved($first, $merged);
                GraphInvariants::assertAllNodesPreserved($second, $merged);
                GraphInvariants::assertBidirectional($merged);
                GraphInvariants::assertNoEdgeDuplicates($merged);
            })
        ;
    }
}
