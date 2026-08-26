<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use Override;
use PhpParser\Node as PhpParserNode;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(DependencyCollector::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\AttributeProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\ClassConstProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\ClassLikeProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\EnumCaseProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\FunctionLikeProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\PromotedPropertyProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Declaration\PropertyProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\CatchProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\ConstFetchProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\FunctionCallProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\InstanceofProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\InstantiationProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\MethodCallProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\PropertyAccessProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticCallProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticPropertyAccessProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\TypeReference::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\SourceResolver::class)]
#[Medium]
final class DependencyCollectorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testGetNodeTypeAsksToBeCalledForEverySyntaxNode(): void
    {
        self::assertSame(PhpParserNode::class, (new DependencyCollector())->getNodeType());
    }

    #[DataProvider('providerDeclaredSymbols')]
    public function testProcessNodeReportsEverySymbolADeclarationBringsIn(string $expected): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            __DIR__.'/../../../../Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains($expected, CollectorRun::nodeNames($collected));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeclaredSymbols(): iterable
    {
        yield 'the attribute class' => ['Tests\Fixture\Source\MyAttribute'];

        yield 'the interface' => ['Tests\Fixture\Source\MyInterface'];

        yield 'the trait' => ['Tests\Fixture\Source\MyTrait'];

        yield 'the enum' => ['Tests\Fixture\Source\MyEnum'];

        yield 'the enum case' => ['Tests\Fixture\Source\MyEnum::A'];

        yield 'the class' => ['Tests\Fixture\Source\ComprehensiveClass'];

        yield 'the class constant' => ['Tests\Fixture\Source\ComprehensiveClass::MY_CONST'];

        yield 'the property' => ['Tests\Fixture\Source\ComprehensiveClass::myProp'];

        yield 'the promoted property' => ['Tests\Fixture\Source\ComprehensiveClass::promotedProp'];

        yield 'the method' => ['Tests\Fixture\Source\ComprehensiveClass::myMethod'];
    }

    #[DataProvider('providerDeclaredRelations')]
    public function testProcessNodeReportsEveryRelationADeclarationWrites(string $expected): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            __DIR__.'/../../../../Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains($expected, CollectorRun::edgeDescriptions($collected));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeclaredRelations(): iterable
    {
        yield 'what the class implements' => ['Tests\Fixture\Source\ComprehensiveClass -[declaration-implements]-> Tests\Fixture\Source\MyInterface'];

        yield 'what the class uses' => ['Tests\Fixture\Source\ComprehensiveClass -[declaration-trait-use]-> Tests\Fixture\Source\MyTrait'];

        yield 'the attribute on the class' => ['Tests\Fixture\Source\ComprehensiveClass -[attribute]-> Tests\Fixture\Source\MyAttribute'];

        yield 'the attribute on the constant' => ['Tests\Fixture\Source\ComprehensiveClass::MY_CONST -[attribute]-> Tests\Fixture\Source\MyAttribute'];

        yield 'the attribute on the property' => ['Tests\Fixture\Source\ComprehensiveClass::myProp -[attribute]-> Tests\Fixture\Source\MyAttribute'];

        yield 'the attribute on the promoted property' => ['Tests\Fixture\Source\ComprehensiveClass::promotedProp -[attribute]-> Tests\Fixture\Source\MyAttribute'];

        yield 'the attribute on the method' => ['Tests\Fixture\Source\ComprehensiveClass::myMethod -[attribute]-> Tests\Fixture\Source\MyAttribute'];

        yield 'the enum declaring its case' => ['Tests\Fixture\Source\MyEnum -[declaration-enum-case]-> Tests\Fixture\Source\MyEnum::A'];

        yield 'the class declaring its method' => ['Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod'];
    }

    public function testDeclarationReadsAClassLikeAsADeclaration(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            __DIR__.'/../../../../Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass', CollectorRun::nodeNames($collected));
    }

    public function testUsageIsLeftToTheOtherCollectorInsideAMethodBody(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            __DIR__.'/../../../../Fixture/Source/MethodBody.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains(
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass',
            CollectorRun::edgeDescriptions($collected),
        );
    }

    public function testUsageIsReportedOutsideAnyMethodBody(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            __DIR__.'/../../../../Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotEmpty(CollectorRun::edgeDescriptions($collected));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerCompleteOutputOfEverySample')]
    public function testProcessNodeReportsExactlyTheseRelations(string $fixture, array $expected): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/'.$fixture.'.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertSame($expected, CollectorRun::sortedEdgeDescriptions($collected));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerCompleteOutputOfEverySample(): iterable
    {
        yield 'every kind of declaration' => ['Comprehensive', [
            'Tests\Fixture\Source\ComprehensiveClass -[attribute]-> Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-constant]-> Tests\Fixture\Source\ComprehensiveClass::MY_CONST',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-implements]-> Tests\Fixture\Source\MyInterface',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::__construct',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-property]-> Tests\Fixture\Source\ComprehensiveClass::myProp',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-property]-> Tests\Fixture\Source\ComprehensiveClass::promotedProp',
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-trait-use]-> Tests\Fixture\Source\MyTrait',
            'Tests\Fixture\Source\ComprehensiveClass::MY_CONST -[attribute]-> Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\ComprehensiveClass::__construct -[attribute]-> Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\ComprehensiveClass::myMethod -[attribute]-> Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\ComprehensiveClass::myProp -[attribute]-> Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\ComprehensiveClass::promotedProp -[attribute]-> Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\MyAttribute -[attribute]-> Attribute',
            'Tests\Fixture\Source\MyEnum -[declaration-enum-case]-> Tests\Fixture\Source\MyEnum::A',
        ]];

        yield 'what a method body reaches' => ['MethodBody', [
            'Tests\Fixture\Source\MethodBodyClass -[declaration-method]-> Tests\Fixture\Source\MethodBodyClass::testMethod',
        ]];

        yield 'every kind of usage' => ['UsageProcessors', [
            'Tests\Fixture\Source\UsageDep -[declaration-method]-> Tests\Fixture\Source\UsageDep::depMethod',
            'Tests\Fixture\Source\UsageDep -[declaration-property]-> Tests\Fixture\Source\UsageDep::instanceProp',
            'Tests\Fixture\Source\UsageDep -[declaration-property]-> Tests\Fixture\Source\UsageDep::staticCount',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-method]-> Tests\Fixture\Source\UsageProcessorFixture::helperMethod',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-method]-> Tests\Fixture\Source\UsageProcessorFixture::testMethod',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-property]-> Tests\Fixture\Source\UsageProcessorFixture::myProp',
        ]];

        yield 'guards and declared types' => ['Guards', [
            'Tests\Fixture\Source\GuardedClass -[declaration-method]-> Tests\Fixture\Source\GuardedClass::guarded',
            'Tests\Fixture\Source\GuardedClass -[declaration-method]-> Tests\Fixture\Source\GuardedClass::guardedBy',
            'Tests\Fixture\Source\GuardedClass::guardedBy -[declaration-type-parameter]-> Tests\Fixture\Source\Guard',
            'Tests\Fixture\Source\GuardedClass::guardedBy -[declaration-type-return]-> Tests\Fixture\Source\GuardedClass',
        ]];

        yield 'inheritance in every shape' => ['Inheritance', [
            'Tests\Fixture\Source\InheritanceAuditable -[declaration-trait-use]-> Tests\Fixture\Source\InheritanceTimestamped',
            'Tests\Fixture\Source\InheritanceDocument -[declaration-method]-> Tests\Fixture\Source\InheritanceDocument::title',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-extends]-> Tests\Fixture\Source\InheritanceDocument',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-implements]-> Tests\Fixture\Source\InheritanceRefundable',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-method]-> Tests\Fixture\Source\InheritanceInvoice::amount',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-method]-> Tests\Fixture\Source\InheritanceInvoice::refund',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-method]-> Tests\Fixture\Source\InheritanceInvoice::title',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-trait-use]-> Tests\Fixture\Source\InheritanceAuditable',
            'Tests\Fixture\Source\InheritancePayable -[declaration-method]-> Tests\Fixture\Source\InheritancePayable::amount',
            'Tests\Fixture\Source\InheritanceRefundable -[declaration-extends]-> Tests\Fixture\Source\InheritancePayable',
            'Tests\Fixture\Source\InheritanceRefundable -[declaration-method]-> Tests\Fixture\Source\InheritanceRefundable::refund',
            'Tests\Fixture\Source\InheritanceStatus -[declaration-enum-case]-> Tests\Fixture\Source\InheritanceStatus::Open',
            'Tests\Fixture\Source\InheritanceStatus -[declaration-enum-case]-> Tests\Fixture\Source\InheritanceStatus::Paid',
            'Tests\Fixture\Source\InheritanceStatus -[declaration-implements]-> Tests\Fixture\Source\InheritancePayable',
            'Tests\Fixture\Source\InheritanceStatus -[declaration-method]-> Tests\Fixture\Source\InheritanceStatus::amount',
        ]];

        yield 'a class that declares nothing' => ['ClassDependency', []];
    }

    public function testProcessNodeReportsEverySymbolItReadAsAnalysed(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertSame(
            CollectorRun::sortedSymbolDescriptions($collected),
            array_values(array_filter(
                CollectorRun::sortedSymbolDescriptions($collected),
                static fn (string $described): bool => str_ends_with($described, '(analysed)'),
            )),
        );
    }
}
