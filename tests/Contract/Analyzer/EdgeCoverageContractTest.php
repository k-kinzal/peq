<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\EdgeCoverageMap;
use Tests\Fixture\Analyzer\PhpParserGrammar;

/**
 * @internal
 */
#[CoversNothing]
#[Medium]
final class EdgeCoverageContractTest extends TestCase
{
    public function testEveryDependencyProducingNodeIsMappedToTheRelationsItProduces(): void
    {
        $unmapped = array_diff(array_keys(PhpParserGrammar::dependencyProducing()), EdgeCoverageMap::mappedNodeClasses());

        self::assertSame([], array_values($unmapped), 'Syntax that produces relations but is mapped to none: '.implode(', ', $unmapped));
    }

    public function testEveryMappedNodeIsOneThatProducesRelations(): void
    {
        $extra = array_diff(EdgeCoverageMap::mappedNodeClasses(), array_keys(PhpParserGrammar::dependencyProducing()));

        self::assertSame([], array_values($extra), 'Syntax mapped to relations that produces none: '.implode(', ', $extra));
    }

    #[DataProviderExternal(EdgeCoverageMap::class, 'mappedEdgeKinds')]
    public function testEveryMappedRelationKindIsPinnedDownSomewhere(string $kindValue): void
    {
        self::assertTrue(
            EdgeCoverageMap::isTested($kindValue) || EdgeCoverageMap::isKnownGap($kindValue) || EdgeCoverageMap::isStructural($kindValue),
            sprintf('The relation kind "%s" is produced by syntax peq reads, but no contract test, known gap or structural check names it.', $kindValue),
        );
    }

    #[DataProviderExternal(EdgeCoverageMap::class, 'pinnedEdgeKinds')]
    public function testEveryPinnedRelationKindNamesAContractTestThatExists(string $kindValue, string $testClass): void
    {
        self::assertTrue(class_exists($testClass), sprintf('The relation kind "%s" names "%s", which does not exist.', $kindValue, $testClass));
        self::assertStringEndsWith('ContractTest', $testClass, sprintf('The relation kind "%s" names "%s", which is not a contract test.', $kindValue, $testClass));
    }

    #[DataProviderExternal(EdgeCoverageMap::class, 'pinnedEdgeKinds')]
    public function testNoPinnedRelationKindIsAlsoAKnownGap(string $kindValue, string $testClass): void
    {
        self::assertFalse(
            EdgeCoverageMap::isKnownGap($kindValue),
            sprintf('The relation kind "%s" is pinned down by %s and should no longer be listed as a gap.', $kindValue, $testClass),
        );
    }

    /**
     * @param list<EdgeKind> $kinds
     */
    #[DataProviderExternal(EdgeCoverageMap::class, 'mappedNodes')]
    public function testNoSyntaxIsMappedToARelationOnlyDerivedFromAnother(string $nodeClass, array $kinds): void
    {
        $derived = array_filter($kinds, static fn (EdgeKind $kind): bool => $kind->direction() === Direction::UsedBy);

        self::assertSame([], array_values($derived), sprintf('"%s" is mapped to a relation that is only ever derived from another.', $nodeClass));
    }

    #[DataProviderExternal(EdgeCoverageMap::class, 'forwardEdgeKinds')]
    public function testEveryRelationKindASourceCanWriteIsProducedBySomeSyntax(EdgeKind $kind): void
    {
        self::assertTrue(
            EdgeCoverageMap::isTested($kind->value) || EdgeCoverageMap::isKnownGap($kind->value) || EdgeCoverageMap::isStructural($kind->value),
            sprintf('No syntax peq reads is mapped to EdgeKind::%s.', $kind->name),
        );
    }

    #[DataProviderExternal(EdgeCoverageMap::class, 'receiverLimitations')]
    public function testEveryRecordedLimitationNamesARelationKindThatIsPinnedDown(string $kindValue): void
    {
        self::assertTrue(EdgeCoverageMap::isTested($kindValue), sprintf('The limitation recorded for "%s" names a relation kind no contract test pins down.', $kindValue));
    }
}
