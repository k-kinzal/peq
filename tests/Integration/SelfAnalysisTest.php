<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Contract\Graph\GraphInvariantAssertions;

/**
 * @internal
 *
 * Integration test: peq analyzes its own source code, then cross-validates
 * the resulting Graph against PHP's Reflection API.
 *
 * This proves that the assembled pipeline (PhpStanAnalyzer → Graph) produces
 * correct results on a real, non-trivial PHP codebase.
 */
final class SelfAnalysisTest extends TestCase
{
    use GraphInvariantAssertions;
    use ReflectionCrossValidator;

    private static Graph $graph;

    public static function setUpBeforeClass(): void
    {
        $analyzer = new PhpStanAnalyzer(
            new ContainerFactory(),
            new PhpFileCollector(),
        );
        self::$graph = $analyzer->analyze(__DIR__.'/../../src');
    }

    // ──────────────────────────────────────────────
    //  Smoke tests
    // ──────────────────────────────────────────────

    #[Test]
    public function testGraphIsNonEmpty(): void
    {
        self::assertNotEmpty(self::$graph->nodes());
        self::assertGreaterThan(50, count(self::$graph->nodes()));
    }

    #[Test]
    public function testGraphContainsKnownClasses(): void
    {
        $knownFqns = [
            'App\Analyzer\Graph\Graph',
            'App\Analyzer\Graph\EdgeKind',
            'App\Analyzer\Graph\NodeKind',
            'App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer',
            'App\Command\InspectCommand',
            'App\Action\Inspect\InspectAction',
        ];

        $nodeIds = array_map(fn ($n) => $n->id()->toString(), self::$graph->nodes());
        foreach ($knownFqns as $fqn) {
            self::assertContains($fqn, $nodeIds, "Expected node {$fqn} not found in graph");
        }
    }

    #[Test]
    public function testGraphInvariantsHold(): void
    {
        self::assertBidirectional(self::$graph);
        self::assertEndpointsExist(self::$graph);
        self::assertNodeUniqueness(self::$graph);
        self::assertNoEdgeDuplicates(self::$graph);
    }

    // ──────────────────────────────────────────────
    //  Soundness: every edge peq found is real
    // ──────────────────────────────────────────────

    #[Test]
    public function testExtendsEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationExtends);
    }

    #[Test]
    public function testImplementsEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationImplements);
    }

    #[Test]
    public function testTraitUseEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationTraitUse);
    }

    #[Test]
    public function testMethodEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationMethod);
    }

    #[Test]
    public function testPropertyEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationProperty);
    }

    #[Test]
    public function testConstantEdgesAreSound(): void
    {
        $result = self::checkSoundness(self::$graph, EdgeKind::DeclarationConstant);
        if ($result['verified'] === 0) {
            self::assertEmpty($result['failures'], 'No constants to verify but failures found');
            self::markTestSkipped('No DeclarationConstant edges in peq codebase');
        }
        self::assertEmpty($result['failures']);
    }

    #[Test]
    public function testEnumCaseEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationEnumCase);
    }

    #[Test]
    public function testAttributeEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::Attribute);
    }

    #[Test]
    public function testParameterTypeEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationTypeParameter);
    }

    #[Test]
    public function testReturnTypeEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationTypeReturn);
    }

    #[Test]
    public function testPropertyTypeEdgesAreSound(): void
    {
        self::assertEdgeKindSoundness(self::$graph, EdgeKind::DeclarationTypeProperty);
    }

    // ──────────────────────────────────────────────
    //  Completeness: every real relationship is in Graph
    // ──────────────────────────────────────────────

    #[Test]
    public function testExtendsEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationExtends);
    }

    #[Test]
    public function testImplementsEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationImplements);
    }

    #[Test]
    public function testTraitUseEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationTraitUse);
    }

    #[Test]
    public function testMethodEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationMethod);
    }

    #[Test]
    public function testPropertyEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationProperty);
    }

    #[Test]
    public function testConstantEdgesAreComplete(): void
    {
        $result = self::checkCompleteness(self::$graph, EdgeKind::DeclarationConstant);
        if ($result['verified'] === 0) {
            self::assertEmpty($result['failures'], 'No constants to verify but failures found');
            self::markTestSkipped('No DeclarationConstant relationships in peq codebase');
        }
        self::assertEmpty($result['failures']);
    }

    #[Test]
    public function testEnumCaseEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationEnumCase);
    }

    #[Test]
    public function testAttributeEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::Attribute);
    }

    #[Test]
    public function testParameterTypeEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationTypeParameter);
    }

    #[Test]
    public function testReturnTypeEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationTypeReturn);
    }

    #[Test]
    public function testPropertyTypeEdgesAreComplete(): void
    {
        self::assertEdgeKindCompleteness(self::$graph, EdgeKind::DeclarationTypeProperty);
    }
}
