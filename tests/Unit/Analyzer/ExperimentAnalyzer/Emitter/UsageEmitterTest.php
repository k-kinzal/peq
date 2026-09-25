<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Emitter;

use App\Analyzer\ExperimentAnalyzer\AnalysisScope;
use App\Analyzer\ExperimentAnalyzer\Emitter\UsageEmitter;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Usage\CatchEdge;
use App\Analyzer\Graph\Edge\Usage\ConstFetchEdge;
use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\Edge\Usage\InstanceofEdge;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\Usage\StaticCallEdge;
use App\Analyzer\Graph\Edge\Usage\StaticPropertyAccessEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Expression;
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
#[CoversClass(UsageEmitter::class)]
#[Medium]
final class UsageEmitterTest extends TestCase
{
    /**
     * @param list<Edge> $expected The relations the expression is expected to describe
     */
    #[DataProvider('providerExpressions')]
    public function testEmitRecordsWhatAnExpressionReachesOutTo(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Expression::class);
        self::assertNotNull($statement);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');

        self::assertEquals($expected, UsageEmitter::emit($statement->expr, $scope));
    }

    /**
     * @return iterable<string, array{string, list<Edge>}>
     */
    public static function providerExpressions(): iterable
    {
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1, 6);

        yield 'instantiating a class' => [
            "<?php\nnew \\App\\Money();\n",
            [new InstantiationEdge($total, new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('/project/Invoice.php', 2, 1, 6, 21))],
        ];

        yield 'instantiating the class it is written in' => [
            "<?php\nnew self();\n",
            [new InstantiationEdge($total, new ClassNode(ClassNodeId::of('App\Invoice'), false, null), new FileMeta('/project/Invoice.php', 2, 1, 6, 15))],
        ];

        yield 'calling a static method' => [
            "<?php\n\\App\\Money::make();\n",
            [new StaticCallEdge($total, new MethodNode(MethodNodeId::of('App\Money', 'make'), false, null), new FileMeta('/project/Invoice.php', 2, 1, 6, 23))],
        ];

        yield 'reading a constant' => [
            "<?php\n\\App\\Money::ZERO;\n",
            [new ConstFetchEdge($total, new ConstantNode(ConstantNodeId::of('App\Money', 'ZERO'), false, null), $at)],
        ];

        yield 'naming a class' => [
            "<?php\n\\App\\Money::class;\n",
            [new ConstFetchEdge($total, new ConstantNode(ConstantNodeId::of('App\Money', 'class'), false, null), $at)],
        ];

        yield 'reading a static property' => [
            "<?php\n\\App\\Money::\$rate;\n",
            [new StaticPropertyAccessEdge($total, new PropertyNode(PropertyNodeId::of('App\Money', 'rate'), false, null), $at)],
        ];

        yield 'testing a type' => [
            "<?php\n\$held instanceof \\App\\Money;\n",
            [new InstanceofEdge($total, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
        ];

        yield 'calling a function' => [
            "<?php\n\\App\\helper();\n",
            [new FunctionCallEdge($total, new FunctionNode(FunctionNodeId::of('App\helper'), false, null), new FileMeta('/project/Invoice.php', 2, 1, 6, 18))],
        ];

        yield 'calling a method on itself' => [
            "<?php\n\$this->helper();\n",
            [new MethodCallEdge($total, new MethodNode(MethodNodeId::of('App\Invoice', 'helper'), false, null), new FileMeta('/project/Invoice.php', 2, 1, 6, 20))],
        ];

        yield 'calling a method on itself, carefully' => [
            "<?php\n\$this?->helper();\n",
            [new MethodCallEdge($total, new MethodNode(MethodNodeId::of('App\Invoice', 'helper'), false, null), new FileMeta('/project/Invoice.php', 2, 1, 6, 21))],
        ];

        yield 'reading a property of itself' => [
            "<?php\n\$this->held;\n",
            [new PropertyAccessEdge($total, new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), false, null), $at)],
        ];

        yield 'reading a property of itself, carefully' => [
            "<?php\n\$this?->held;\n",
            [new PropertyAccessEdge($total, new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), false, null), $at)],
        ];
    }

    #[DataProvider('providerExpressionsThatReachNothingNameable')]
    public function testEmitRecordsNothingForAnExpressionThatNamesNothing(string $code): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Expression::class);
        self::assertNotNull($statement);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');

        self::assertSame([], UsageEmitter::emit($statement->expr, $scope));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerExpressionsThatReachNothingNameable(): iterable
    {
        yield 'instantiating a class named by a variable' => ["<?php\nnew \$held();\n"];

        yield 'calling a method on an object of unknown type' => ["<?php\n\$other->helper();\n"];

        yield 'reading a property of an object of unknown type' => ["<?php\n\$other->held;\n"];

        yield 'calling a static method on a class named by a variable' => ["<?php\n\$held::make();\n"];

        yield 'calling a function named by a variable' => ["<?php\n\$held();\n"];

        yield 'reading a constant of a class named by a variable' => ["<?php\n\$held::ZERO;\n"];

        yield 'adding two numbers' => ["<?php\n1 + 1;\n"];
    }

    public function testEmitRecordsNothingWhereThereIsNoSymbolToRecordItAgainst(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnew \\App\\Money();\n") ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Expression::class);
        self::assertNotNull($statement);

        self::assertSame([], UsageEmitter::emit($statement->expr, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')));
    }

    #[DataProvider('providerExpressionsWorthLookingAt')]
    public function testRecordsRecognisesAnExpressionWorthLookingAt(string $code, bool $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Expression::class);
        self::assertNotNull($statement);

        self::assertSame($expected, UsageEmitter::records($statement->expr));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerExpressionsWorthLookingAt(): iterable
    {
        yield 'reading a constant' => ["<?php\n\\App\\Money::ZERO;\n", true];

        yield 'instantiating a class' => ["<?php\nnew \\App\\Money();\n", true];

        yield 'calling a static method' => ["<?php\n\\App\\Money::make();\n", true];

        yield 'testing a type' => ["<?php\n\$held instanceof \\App\\Money;\n", true];

        yield 'calling a function' => ["<?php\n\\App\\helper();\n", true];

        yield 'calling a method' => ["<?php\n\$this->helper();\n", true];

        yield 'calling a method carefully' => ["<?php\n\$this?->helper();\n", true];

        yield 'reading a property' => ["<?php\n\$this->held;\n", true];

        yield 'reading a property carefully' => ["<?php\n\$this?->held;\n", true];

        yield 'reading a static property' => ["<?php\n\\App\\Money::\$rate;\n", true];

        yield 'adding two numbers' => ["<?php\n1 + 1;\n", false];

        yield 'reading a variable' => ["<?php\n\$held;\n", false];

        yield 'writing a string' => ["<?php\n'text';\n", false];

        yield 'calling something a variable names is still a call' => ["<?php\n\$held();\n", true];
    }

    public function testConstantFetchRecordsAConstantBeingRead(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\\App\\Money::ZERO;\n") ?? []);
        $fetch = (new NodeFinder())->findFirstInstanceOf($parsed, ClassConstFetch::class);
        self::assertNotNull($fetch);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new ConstFetchEdge($total, new ConstantNode(ConstantNodeId::of('App\Money', 'ZERO'), false, null), $at)],
            UsageEmitter::constantFetch($fetch, $scope, $total, $at),
        );
    }

    public function testInstantiationRecordsAClassBeingMade(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnew \\App\\Money();\n") ?? []);
        $made = (new NodeFinder())->findFirstInstanceOf($parsed, New_::class);
        self::assertNotNull($made);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new InstantiationEdge($total, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
            UsageEmitter::instantiation($made, $scope, $total, $at),
        );
    }

    public function testStaticCallRecordsAStaticMethodBeingCalled(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\\App\\Money::make();\n") ?? []);
        $call = (new NodeFinder())->findFirstInstanceOf($parsed, StaticCall::class);
        self::assertNotNull($call);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new StaticCallEdge($total, new MethodNode(MethodNodeId::of('App\Money', 'make'), false, null), $at)],
            UsageEmitter::staticCall($call, $scope, $total, $at),
        );
    }

    public function testInstanceOfRecordsATypeBeingTested(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\$held instanceof \\App\\Money;\n") ?? []);
        $test = (new NodeFinder())->findFirstInstanceOf($parsed, Instanceof_::class);
        self::assertNotNull($test);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new InstanceofEdge($total, new ClassNode(ClassNodeId::of('App\Money'), false, null), $at)],
            UsageEmitter::instanceOf($test, $scope, $total, $at),
        );
    }

    public function testFunctionCallRecordsAFunctionBeingCalled(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\\App\\helper();\n") ?? []);
        $call = (new NodeFinder())->findFirstInstanceOf($parsed, FuncCall::class);
        self::assertNotNull($call);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new FunctionCallEdge($total, new FunctionNode(FunctionNodeId::of('App\helper'), false, null), $at)],
            UsageEmitter::functionCall($call, $scope, $total, $at),
        );
    }

    public function testMethodCallRecordsAMethodBeingCalledOnItself(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\$this->helper();\n") ?? []);
        $call = (new NodeFinder())->findFirstInstanceOf($parsed, MethodCall::class);
        self::assertNotNull($call);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new MethodCallEdge($total, new MethodNode(MethodNodeId::of('App\Invoice', 'helper'), false, null), $at)],
            UsageEmitter::methodCall($call, $scope, $total, $at),
        );
    }

    public function testPropertyAccessRecordsAPropertyOfItselfBeingRead(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\$this->held;\n") ?? []);
        $fetch = (new NodeFinder())->findFirstInstanceOf($parsed, PropertyFetch::class);
        self::assertNotNull($fetch);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new PropertyAccessEdge($total, new PropertyNode(PropertyNodeId::of('App\Invoice', 'held'), false, null), $at)],
            UsageEmitter::propertyAccess($fetch, $scope, $total, $at),
        );
    }

    public function testStaticPropertyAccessRecordsAStaticPropertyBeingRead(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\n\\App\\Money::\$rate;\n") ?? []);
        $fetch = (new NodeFinder())->findFirstInstanceOf($parsed, StaticPropertyFetch::class);
        self::assertNotNull($fetch);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Invoice.php')->enteringClass('App\Invoice', null)->enteringMethod('total');
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 2, 1);

        self::assertEquals(
            [new StaticPropertyAccessEdge($total, new PropertyNode(PropertyNodeId::of('App\Money', 'rate'), false, null), $at)],
            UsageEmitter::staticPropertyAccess($fetch, $scope, $total, $at),
        );
    }

    public function testCaughtRecordsEveryTypeACatchClauseNames(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\ntry { \$written = 1; } catch (\\App\\Failure|\\LogicException \$caught) { \$caught->getMessage(); }\n") ?? []);
        $clause = (new NodeFinder())->findFirstInstanceOf($parsed, Catch_::class);
        self::assertNotNull($clause);
        $total = new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null);
        $at = new FileMeta('/project/Invoice.php', 3, 1);

        self::assertEquals(
            [
                new CatchEdge($total, new ClassNode(ClassNodeId::of('App\Failure'), false, null), $at),
                new CatchEdge($total, new ClassNode(ClassNodeId::of('LogicException'), false, null), $at),
            ],
            UsageEmitter::caught($clause, $total, $at),
        );
    }

    public function testIsThisRecognisesTheObjectTheCodeIsWrittenIn(): void
    {
        $parsed = (new ParserFactory())->createForHostVersion()->parse("<?php\n\$this;\n") ?? [];
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Expression::class);
        self::assertNotNull($statement);

        self::assertTrue(UsageEmitter::isThis($statement->expr));
    }

    public function testIsThisDoesNotTakeAnotherVariableForIt(): void
    {
        $parsed = (new ParserFactory())->createForHostVersion()->parse("<?php\n\$other;\n") ?? [];
        $statement = (new NodeFinder())->findFirstInstanceOf($parsed, Expression::class);
        self::assertNotNull($statement);

        self::assertFalse(UsageEmitter::isThis($statement->expr));
    }

    public function testRecordsRecognisesACatchClauseWorthLookingAt(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\ntry { \$written = 1; } catch (\\App\\Failure \$caught) { \$caught->getMessage(); }\n") ?? []);
        $clause = (new NodeFinder())->findFirstInstanceOf($parsed, Catch_::class);
        self::assertNotNull($clause);

        self::assertTrue(UsageEmitter::records($clause));
    }

    public function testRecordsDoesNotTakeAnyStatementForOneWorthLookingAt(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Written { public int \$held = 0; }\n") ?? []);
        $property = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($property);

        self::assertFalse(UsageEmitter::records($property));
    }
}
