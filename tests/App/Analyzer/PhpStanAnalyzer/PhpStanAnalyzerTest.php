<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpFileCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PhpStanAnalyzerTest extends TestCase
{
    public function testAnalyze(): void
    {
        $analyzer = new PhpStanAnalyzer(
            new ContainerFactory(),
            new PhpFileCollector(),
        );

        $graph = $analyzer->analyze(__DIR__.'/Fixture');

        $nodes = $graph->nodes();
        self::assertNotEmpty($nodes);

        // Check for TestClass node
        $classNode = null;
        foreach ($nodes as $node) {
            if ($node->id()->toString() === 'Tests\App\Analyzer\PhpStanAnalyzer\Fixture\TestClass') {
                $classNode = $node;

                break;
            }
        }
        self::assertNotNull($classNode);

        // Check edges
        $edges = $graph->edges($classNode->id());
        // We expect:
        // - Instantiation (new self) -> but self resolves to class, so it's InstantiationEdge
        // - ConstFetch (self::CONSTANT)
        // - StaticCall (static::staticMethod)
        // But wait, the edges are FROM the method, not the class.
        // The source of the edges is the method `method`.

        // Let's find the method node
        $methodNode = null;
        foreach ($nodes as $node) {
            if ($node->id()->toString() === 'Tests\App\Analyzer\PhpStanAnalyzer\Fixture\TestClass::method') {
                $methodNode = $node;

                break;
            }
        }
        self::assertNotNull($methodNode);

        $methodEdges = $graph->edges($methodNode->id());
        self::assertNotEmpty($methodEdges);

        $edgeKinds = array_map(fn ($e) => $e->kind(), $methodEdges);

        self::assertContains(EdgeKind::Instantiation, $edgeKinds);
        self::assertContains(EdgeKind::ConstFetch, $edgeKinds);
        self::assertContains(EdgeKind::StaticCall, $edgeKinds);
    }

    public function testAnalyzeComplexFixture(): void
    {
        $analyzer = new PhpStanAnalyzer(
            new ContainerFactory(),
            new PhpFileCollector()
        );

        $graph = $analyzer->analyze(__DIR__.'/Fixture/ComplexFixture.php');
        $nodes = $graph->nodes();

        // Helper to find node by ID suffix
        $findNode = function (string $suffix) use ($nodes) {
            foreach ($nodes as $node) {
                if (str_ends_with($node->id()->toString(), $suffix)) {
                    return $node;
                }
            }

            return null;
        };

        $ids = array_map(fn ($n) => $n->id()->toString(), $nodes);
        $idsList = implode(', ', $ids);

        // Assert Nodes exist
        self::assertNotNull($findNode('ComplexClass'), 'ComplexClass node missing. Available: '.$idsList);
        self::assertNotNull($findNode('MyInterface'), 'MyInterface node missing. Available: '.$idsList);
        self::assertNotNull($findNode('MyTrait'), 'MyTrait node missing. Available: '.$idsList);
        self::assertNotNull($findNode('MyEnum'), 'MyEnum node missing. Available: '.$idsList);
        self::assertNotNull($findNode('MyAttribute'), 'MyAttribute node missing. Available: '.$idsList);
        self::assertNotNull($findNode('ComplexClass::MY_CONST'), 'Constant node missing. Available: '.$idsList);
        self::assertNotNull($findNode('ComplexClass::myProp'), 'Property node missing. Available: '.$idsList);
        self::assertNotNull($findNode('ComplexClass::promotedProp'), 'Promoted Property node missing. Available: '.$idsList);
        self::assertNotNull($findNode('ComplexClass::complexMethod'), 'Method node missing. Available: '.$idsList);
        self::assertNotNull($findNode('MyEnum::A'), 'EnumCase node missing. Available: '.$idsList);

        // Assert Edges
        $classNode = $findNode('ComplexClass');
        $edges = $graph->edges($classNode->id());
        $edgeKinds = array_map(fn ($e) => $e->kind(), $edges);

        self::assertContains(EdgeKind::DeclarationImplements, $edgeKinds);
        self::assertContains(EdgeKind::DeclarationTraitUse, $edgeKinds);
        self::assertContains(EdgeKind::Attribute, $edgeKinds);
        self::assertContains(EdgeKind::DeclarationConstant, $edgeKinds);
        self::assertContains(EdgeKind::DeclarationProperty, $edgeKinds);
        self::assertContains(EdgeKind::DeclarationMethod, $edgeKinds);

        $methodNode = $findNode('ComplexClass::complexMethod');
        $methodEdges = $graph->edges($methodNode->id());
        $methodEdgeKinds = array_map(fn ($e) => $e->kind(), $methodEdges);

        self::assertContains(EdgeKind::Attribute, $methodEdgeKinds);
        self::assertContains(EdgeKind::DeclarationTypeParameter, $methodEdgeKinds);
        self::assertContains(EdgeKind::Instanceof, $methodEdgeKinds);
        self::assertContains(EdgeKind::Catch, $methodEdgeKinds);
        self::assertContains(EdgeKind::Instantiation, $methodEdgeKinds);
    }
}
