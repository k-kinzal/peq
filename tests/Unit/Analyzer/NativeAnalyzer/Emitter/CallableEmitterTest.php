<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Declaration\PropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeParameterEdge;
use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeReturnEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\Emitter\CallableEmitter;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
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
#[CoversClass(CallableEmitter::class)]
#[Medium]
final class CallableEmitterTest extends TestCase
{
    /**
     * @param list<Edge|Node> $expected What the declaration is expected to record
     */
    #[DataProvider('providerMethods')]
    public function testMethodRecordsADeclarationAndWhatItsSignatureCommitsTo(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $method = (new NodeFinder())->findFirstInstanceOf($parsed, ClassMethod::class);
        self::assertNotNull($method);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);

        self::assertEquals($expected, CallableEmitter::method($method, $scope, NodeKind::Klass));
    }

    /**
     * @return iterable<string, array{string, list<Edge|Node>}>
     */
    public static function providerMethods(): iterable
    {
        $invoice = new ClassNode(ClassNodeId::of('App\Invoice'), true, null);
        $at = new FileMeta('/project/Invoice.php', 3, 1);

        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([], 'void')));

        yield 'a method' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void {} }\n",
            [new MethodEdge($invoice, $total, $at), $total],
        ];

        $returning = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([], 'App\Money')));

        yield 'a method that returns a class' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): \\App\\Money {} }\n",
            [new MethodEdge($invoice, $returning, $at), $returning, new TypeReturnEdge($returning, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
        ];

        $taking = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([new Parameter('money', 'App\Money')], 'void')));

        yield 'a method that takes a class' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(\\App\\Money \$money): void {} }\n",
            [new MethodEdge($invoice, $taking, $at), $taking, new TypeParameterEdge($taking, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
        ];

        $marked = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([], 'void'), attributes: [new AttributeUsage('App\Marker')]));

        yield 'a method carrying an attribute' => [
            "<?php\nnamespace App;\nclass Invoice { #[\\App\\Marker] public function total(): void {} }\n",
            [new MethodEdge($invoice, $marked, $at), $marked, new AttributeEdge($marked, new ClassNode(ClassNodeId::of('App\Marker'), false, null), $at)],
        ];

        $markedParameter = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([new Parameter('amount', 'int')], 'void')));

        yield 'a method whose parameter carries an attribute' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(#[\\App\\Marker] int \$amount): void {} }\n",
            [new MethodEdge($invoice, $markedParameter, $at), $markedParameter, new AttributeEdge($markedParameter, new ClassNode(ClassNodeId::of('App\Marker'), false, null), $at)],
        ];

        $builtin = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([new Parameter('amount', 'int')], 'string')));

        yield 'a method whose signature names only builtin types' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(int \$amount): string {} }\n",
            [new MethodEdge($invoice, $builtin, $at), $builtin],
        ];
    }

    public function testMethodRecordsADeclarationAgainstTheKindOfWhatDeclaresIt(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\ninterface Invoice { public function total(): void; }\n") ?? []);
        $method = (new NodeFinder())->findFirstInstanceOf($parsed, ClassMethod::class);
        self::assertNotNull($method);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);
        $at = new FileMeta('/project/Invoice.php', 3, 1);
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, $at, new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([], 'void')));

        self::assertEquals(
            [new MethodEdge(new GraphInterfaceNode(InterfaceNodeId::of('App\Invoice'), true, null), $total, $at), $total],
            CallableEmitter::method($method, $scope, NodeKind::Interface),
        );
    }

    public function testMethodRecordsNothingOutsideAClass(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice { public function total(): void {} }\n") ?? []);
        $method = (new NodeFinder())->findFirstInstanceOf($parsed, ClassMethod::class);
        self::assertNotNull($method);

        self::assertSame([], CallableEmitter::method($method, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php'), NodeKind::Klass));
    }

    /**
     * @param list<Edge|Node> $expected What the declaration is expected to record
     */
    #[DataProvider('providerFunctions')]
    public function testGlobalFunctionRecordsADeclarationAndWhatItsSignatureCommitsTo(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $declaration = (new NodeFinder())->findFirstInstanceOf($parsed, Function_::class);
        self::assertNotNull($declaration);

        self::assertEquals($expected, CallableEmitter::globalFunction($declaration, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/helper.php')));
    }

    /**
     * @return iterable<string, array{string, list<Edge|Node>}>
     */
    public static function providerFunctions(): iterable
    {
        $at = new FileMeta('/project/helper.php', 3, 1);

        yield 'a function' => [
            "<?php\nnamespace App;\nfunction helper(): void {}\n",
            [new FunctionNode(FunctionNodeId::of('App\helper'), true, $at, new SymbolDeclaration(signature: new Signature([], 'void')))],
        ];

        $returning = new FunctionNode(FunctionNodeId::of('App\helper'), true, $at, new SymbolDeclaration(signature: new Signature([], 'App\Money')));

        yield 'a function that returns a class' => [
            "<?php\nnamespace App;\nfunction helper(): \\App\\Money {}\n",
            [$returning, new TypeReturnEdge($returning, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
        ];
    }

    /**
     * @param list<Edge|Node> $expected What the declaration is expected to record
     */
    #[DataProvider('providerPromotedParameters')]
    public function testPromotedPropertyRecordsThePropertyAParameterDeclares(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $param = (new NodeFinder())->findFirstInstanceOf($parsed, Param::class);
        self::assertNotNull($param);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);

        self::assertEquals($expected, CallableEmitter::promotedProperty($param, $scope));
    }

    /**
     * @return iterable<string, array{string, list<Edge|Node>}>
     */
    public static function providerPromotedParameters(): iterable
    {
        $invoice = new ClassNode(ClassNodeId::of('App\Invoice'), true, null);
        $at = new FileMeta('/project/Invoice.php', 3, 1);

        $held = new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), true, $at, new SymbolDeclaration(visibility: Visibility::Private, type: 'int'));

        yield 'a parameter with a visibility declares a property' => [
            "<?php\nnamespace App;\nclass Invoice { public function __construct(private int \$held) {} }\n",
            [$held, new PropertyEdge($invoice, $held, $at)],
        ];

        $money = new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), true, $at, new SymbolDeclaration(visibility: Visibility::Private, type: 'App\Money'));

        yield 'a parameter typed as a class' => [
            "<?php\nnamespace App;\nclass Invoice { public function __construct(private \\App\\Money \$held) {} }\n",
            [$money, new PropertyEdge($invoice, $money, $at), new TypePropertyEdge($money, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
        ];

        $marked = new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), true, $at, new SymbolDeclaration(visibility: Visibility::Private, attributes: [new AttributeUsage('App\Marker')], type: 'int'));

        yield 'a parameter carrying an attribute' => [
            "<?php\nnamespace App;\nclass Invoice { public function __construct(#[\\App\\Marker] private int \$held) {} }\n",
            [$marked, new PropertyEdge($invoice, $marked, $at), new AttributeEdge($marked, new ClassNode(ClassNodeId::of('App\Marker'), false, null), $at)],
        ];

        yield 'a parameter with no visibility declares nothing' => ["<?php\nnamespace App;\nclass Invoice { public function total(int \$amount): void {} }\n", []];
    }

    public function testPromotedPropertyRecordsNothingOutsideAClass(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice { public function __construct(private int \$held) {} }\n") ?? []);
        $param = (new NodeFinder())->findFirstInstanceOf($parsed, Param::class);
        self::assertNotNull($param);

        self::assertSame([], CallableEmitter::promotedProperty($param, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')));
    }

    public function testSignatureRecordsEachTypeWhereItIsWritten(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice {\n    public function total(\n        \\App\\Rate \$rate,\n    ): \\App\\Money {}\n}\n") ?? []);
        $method = (new NodeFinder())->findFirstInstanceOf($parsed, ClassMethod::class);
        self::assertNotNull($method);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);

        self::assertEquals(
            [
                new TypeReturnEdge($total, new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('/project/Invoice.php', 6, 1)),
                new TypeParameterEdge($total, new ClassNode(ClassNodeId::of('App\Rate'), false, null), new FileMeta('/project/Invoice.php', 5, 1)),
            ],
            CallableEmitter::signature($method, $total, $scope, new FileMeta('/project/Invoice.php', 4, 1)),
        );
    }

    public function testSignatureRecordsNothingForASignatureThatNamesOnlyBuiltinTypes(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice { public function total(int \$amount): string {} }\n") ?? []);
        $method = (new NodeFinder())->findFirstInstanceOf($parsed, ClassMethod::class);
        self::assertNotNull($method);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null);

        self::assertSame([], CallableEmitter::signature($method, new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null), $scope, new FileMeta('/project/Invoice.php', 3, 1)));
    }
}
