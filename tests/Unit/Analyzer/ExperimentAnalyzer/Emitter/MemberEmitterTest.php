<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Emitter;

use App\Analyzer\ExperimentAnalyzer\AnalysisScope;
use App\Analyzer\ExperimentAnalyzer\Emitter\MemberEmitter;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\ConstantEdge;
use App\Analyzer\Graph\Edge\Declaration\EnumCaseEdge;
use App\Analyzer\Graph\Edge\Declaration\PropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MemberEmitter::class)]
#[Medium]
final class MemberEmitterTest extends TestCase
{
    /**
     * @param list<Edge|Node> $expected What the statement is expected to record
     */
    #[DataProvider('providerProperties')]
    public function testPropertiesRecordsEveryOneAStatementDeclares(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($statement);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);

        self::assertEquals($expected, MemberEmitter::properties($statement, $scope));
    }

    /**
     * @return iterable<string, array{string, list<Edge|Node>}>
     */
    public static function providerProperties(): iterable
    {
        $invoice = new ClassNode(ClassNodeId::of('App\Invoice'), true, null);
        $at = new FileMeta('/project/Invoice.php', 3, 1);

        $held = new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, type: 'int'));

        yield 'one property' => [
            "<?php\nnamespace App;\nclass Invoice { public int \$held = 0; }\n",
            [$held, new PropertyEdge($invoice, $held, $at)],
        ];

        $first = new PropertyNode(PropertyNodeId::of('App\Invoice', 'first'), true, $at, new SymbolDeclaration(visibility: Visibility::Public));
        $second = new PropertyNode(PropertyNodeId::of('App\Invoice', 'second'), true, $at, new SymbolDeclaration(visibility: Visibility::Public));

        yield 'two properties in one statement' => [
            "<?php\nnamespace App;\nclass Invoice { public \$first, \$second; }\n",
            [$first, new PropertyEdge($invoice, $first, $at), $second, new PropertyEdge($invoice, $second, $at)],
        ];

        $money = new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, type: 'App\Money'));

        yield 'a property typed as a class' => [
            "<?php\nnamespace App;\nclass Invoice { public \\App\\Money \$held; }\n",
            [$money, new PropertyEdge($invoice, $money, $at), new TypePropertyEdge($money, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
        ];

        $marked = new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, attributes: [new AttributeUsage('App\Marker')], type: 'int'));

        yield 'a property carrying an attribute' => [
            "<?php\nnamespace App;\nclass Invoice { #[\\App\\Marker] public int \$held = 0; }\n",
            [$marked, new PropertyEdge($invoice, $marked, $at), new AttributeEdge($marked, new ClassNode(ClassNodeId::of('App\Marker'), false, null), $at)],
        ];
    }

    /**
     * @param list<Edge|Node> $expected What the statement is expected to record
     */
    #[DataProvider('providerConstants')]
    public function testConstantsRecordsEveryOneAStatementDeclares(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, ClassConst::class);
        self::assertNotNull($statement);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);

        self::assertEquals($expected, MemberEmitter::constants($statement, $scope));
    }

    /**
     * @return iterable<string, array{string, list<Edge|Node>}>
     */
    public static function providerConstants(): iterable
    {
        $invoice = new ClassNode(ClassNodeId::of('App\Invoice'), true, null);
        $at = new FileMeta('/project/Invoice.php', 3, 1);

        $kind = new ConstantNode(ConstantNodeId::of('App\Invoice', 'KIND'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, value: "'invoice'"));

        yield 'one constant' => [
            "<?php\nnamespace App;\nclass Invoice { public const KIND = 'invoice'; }\n",
            [$kind, new ConstantEdge($invoice, $kind, $at)],
        ];

        $first = new ConstantNode(ConstantNodeId::of('App\Invoice', 'FIRST'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, value: '1'));
        $second = new ConstantNode(ConstantNodeId::of('App\Invoice', 'SECOND'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, value: '2'));

        yield 'two constants in one statement' => [
            "<?php\nnamespace App;\nclass Invoice { public const FIRST = 1, SECOND = 2; }\n",
            [$first, new ConstantEdge($invoice, $first, $at), $second, new ConstantEdge($invoice, $second, $at)],
        ];

        $marked = new ConstantNode(ConstantNodeId::of('App\Invoice', 'KIND'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, attributes: [new AttributeUsage('App\Marker')], value: "'invoice'"));

        yield 'a constant carrying an attribute' => [
            "<?php\nnamespace App;\nclass Invoice { #[\\App\\Marker] public const KIND = 'invoice'; }\n",
            [$marked, new ConstantEdge($invoice, $marked, $at), new AttributeEdge($marked, new ClassNode(ClassNodeId::of('App\Marker'), false, null), $at)],
        ];
    }

    public function testEnumCaseRecordsTheCaseAnEnumDeclares(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nenum Status { case Open; }\n") ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, EnumCase::class);
        self::assertNotNull($statement);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Status.php')->enteringClass('App\Status', null);
        $open = new EnumCaseNode(EnumCaseNodeId::of('App\Status', 'Open'), true, new FileMeta('/project/Status.php', 3, 1), new SymbolDeclaration());

        self::assertEquals(
            [$open, new EnumCaseEdge(new EnumNode(EnumNodeId::of('App\Status'), true, null), $open, new FileMeta('/project/Status.php', 3, 1))],
            MemberEmitter::enumCase($statement, $scope),
        );
    }

    public function testPropertiesRecordsNothingOutsideAClass(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice { public int \$held = 0; }\n") ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($statement);

        self::assertSame([], MemberEmitter::properties($statement, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')));
    }

    public function testConstantsRecordsNothingOutsideAClass(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice { public const KIND = 'invoice'; }\n") ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, ClassConst::class);
        self::assertNotNull($statement);

        self::assertSame([], MemberEmitter::constants($statement, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')));
    }

    public function testEnumCaseRecordsNothingOutsideAClass(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nenum Status { case Open; }\n") ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, EnumCase::class);
        self::assertNotNull($statement);

        self::assertSame([], MemberEmitter::enumCase($statement, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Status.php')));
    }
}
