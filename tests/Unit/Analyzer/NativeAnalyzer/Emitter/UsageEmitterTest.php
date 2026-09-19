<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\NativeAnalyzer\Emitter\UsageEmitter;
use PhpParser\Node as PhpParserNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GraphSpelling;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(UsageEmitter::class)]
#[Medium]
final class UsageEmitterTest extends TestCase
{
    /**
     * @param list<string> $expected The relations the expression is expected to describe
     */
    #[DataProvider('providerExpressions')]
    public function testEmitRecordsWhatAnExpressionReachesOutTo(string $written, array $expected): void
    {
        self::assertSame($expected, GraphSpelling::of(UsageEmitter::emit(ParsedSnippet::expression($written), ParsedSnippet::scopeIn('App\Invoice', 'total'))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'instantiating a class' => ['new \App\Money()', ['App\Invoice::total -[instantiation]-> App\Money']];

        yield 'instantiating the class it is written in' => ['new self()', ['App\Invoice::total -[instantiation]-> App\Invoice']];

        yield 'calling a static method' => ['\App\Money::make()', ['App\Invoice::total -[static-call]-> App\Money::make']];

        yield 'reading a constant' => ['\App\Money::ZERO', ['App\Invoice::total -[const-fetch]-> App\Money::ZERO']];

        yield 'naming a class' => ['\App\Money::class', ['App\Invoice::total -[const-fetch]-> App\Money::class']];

        yield 'reading a static property' => ['\App\Money::$rate', ['App\Invoice::total -[static-property-access]-> App\Money::rate']];

        yield 'testing a type' => ['$held instanceof \App\Money', ['App\Invoice::total -[instanceof]-> App\Money']];

        yield 'calling a function' => ['\App\helper()', ['App\Invoice::total -[function-call]-> App\helper']];

        yield 'calling a method on itself' => ['$this->helper()', ['App\Invoice::total -[method-call]-> App\Invoice::helper']];

        yield 'calling a method on itself, carefully' => ['$this?->helper()', ['App\Invoice::total -[method-call]-> App\Invoice::helper']];

        yield 'reading a property of itself' => ['$this->held', ['App\Invoice::total -[property-access]-> App\Invoice::held']];

        yield 'reading a property of itself, carefully' => ['$this?->held', ['App\Invoice::total -[property-access]-> App\Invoice::held']];
    }

    #[DataProvider('providerExpressionsThatReachNothingNameable')]
    public function testEmitRecordsNothingForAnExpressionThatNamesNothing(string $written): void
    {
        self::assertSame([], UsageEmitter::emit(ParsedSnippet::expression($written), ParsedSnippet::scopeIn('App\Invoice', 'total')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerExpressionsThatReachNothingNameable(): iterable
    {
        yield 'instantiating a class named by a variable' => ['new $held()'];

        yield 'calling a method on an object of unknown type' => ['$other->helper()'];

        yield 'reading a property of an object of unknown type' => ['$other->held'];

        yield 'calling a static method on a class named by a variable' => ['$held::make()'];

        yield 'calling a function named by a variable' => ['$held()'];

        yield 'reading a constant of a class named by a variable' => ['$held::ZERO'];

        yield 'adding two numbers' => ['1 + 1'];
    }

    public function testEmitRecordsNothingWhereThereIsNoSymbolToRecordItAgainst(): void
    {
        self::assertSame([], UsageEmitter::emit(ParsedSnippet::expression('new \App\Money()'), ParsedSnippet::scopeIn(null, null)));
    }

    #[DataProvider('providerExpressionsWorthLookingAt')]
    public function testRecordsRecognisesAnExpressionWorthLookingAt(string $written, bool $expected): void
    {
        self::assertSame($expected, UsageEmitter::records(ParsedSnippet::expression($written)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerExpressionsWorthLookingAt(): iterable
    {
        yield 'instantiating a class' => ['new \App\Money()', true];

        yield 'calling a method' => ['$this->helper()', true];

        yield 'adding two numbers' => ['1 + 1', false];

        yield 'reading a variable' => ['$held', false];
    }

    public function testConstantFetchRecordsAConstantBeingRead(): void
    {
        $node = ParsedSnippet::expression('\App\Money::ZERO');
        self::assertInstanceOf(PhpParserNode\Expr\ClassConstFetch::class, $node);

        self::assertSame(
            ['App\Invoice::total -[const-fetch]-> App\Money::ZERO'],
            GraphSpelling::of(UsageEmitter::constantFetch($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testInstantiationRecordsAClassBeingMade(): void
    {
        $node = ParsedSnippet::expression('new \App\Money()');
        self::assertInstanceOf(PhpParserNode\Expr\New_::class, $node);

        self::assertSame(
            ['App\Invoice::total -[instantiation]-> App\Money'],
            GraphSpelling::of(UsageEmitter::instantiation($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testStaticCallRecordsAStaticMethodBeingCalled(): void
    {
        $node = ParsedSnippet::expression('\App\Money::make()');
        self::assertInstanceOf(PhpParserNode\Expr\StaticCall::class, $node);

        self::assertSame(
            ['App\Invoice::total -[static-call]-> App\Money::make'],
            GraphSpelling::of(UsageEmitter::staticCall($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testInstanceOfRecordsATypeBeingTested(): void
    {
        $node = ParsedSnippet::expression('$held instanceof \App\Money');
        self::assertInstanceOf(PhpParserNode\Expr\Instanceof_::class, $node);

        self::assertSame(
            ['App\Invoice::total -[instanceof]-> App\Money'],
            GraphSpelling::of(UsageEmitter::instanceOf($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testFunctionCallRecordsAFunctionBeingCalled(): void
    {
        $node = ParsedSnippet::expression('\App\helper()');
        self::assertInstanceOf(PhpParserNode\Expr\FuncCall::class, $node);

        self::assertSame(
            ['App\Invoice::total -[function-call]-> App\helper'],
            GraphSpelling::of(UsageEmitter::functionCall($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testMethodCallRecordsAMethodBeingCalledOnItself(): void
    {
        $node = ParsedSnippet::expression('$this->helper()');
        self::assertInstanceOf(PhpParserNode\Expr\MethodCall::class, $node);

        self::assertSame(
            ['App\Invoice::total -[method-call]-> App\Invoice::helper'],
            GraphSpelling::of(UsageEmitter::methodCall($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testPropertyAccessRecordsAPropertyOfItselfBeingRead(): void
    {
        $node = ParsedSnippet::expression('$this->held');
        self::assertInstanceOf(PhpParserNode\Expr\PropertyFetch::class, $node);

        self::assertSame(
            ['App\Invoice::total -[property-access]-> App\Invoice::held'],
            GraphSpelling::of(UsageEmitter::propertyAccess($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testStaticPropertyAccessRecordsAStaticPropertyBeingRead(): void
    {
        $node = ParsedSnippet::expression('\App\Money::$rate');
        self::assertInstanceOf(PhpParserNode\Expr\StaticPropertyFetch::class, $node);

        self::assertSame(
            ['App\Invoice::total -[static-property-access]-> App\Money::rate'],
            GraphSpelling::of(UsageEmitter::staticPropertyAccess($node, ParsedSnippet::scopeIn('App\Invoice', 'total'), ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testCaughtRecordsEveryTypeACatchClauseNames(): void
    {
        $clause = ParsedSnippet::memberStatement(
            "<?php\nnamespace App;\ntry { \$written = 1; } catch (\\App\\Failure|\\LogicException \$caught) { \$caught->getMessage(); }\n",
            PhpParserNode\Stmt\Catch_::class,
        );

        self::assertSame(
            ['App\Invoice::total -[catch]-> App\Failure', 'App\Invoice::total -[catch]-> LogicException'],
            GraphSpelling::of(UsageEmitter::caught($clause, ParsedSnippet::writtenBy('App\Invoice', 'total'), ParsedSnippet::writtenAt())),
        );
    }

    public function testIsThisRecognisesTheObjectTheCodeIsWrittenIn(): void
    {
        self::assertTrue(UsageEmitter::isThis(ParsedSnippet::expression('$this')));
    }

    public function testIsThisDoesNotTakeAnotherVariableForIt(): void
    {
        self::assertFalse(UsageEmitter::isThis(ParsedSnippet::expression('$other')));
    }
}
