<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use Override;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(InClassMethodCollector::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\InClassMethodNodeProcessor::class)]
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
final class InClassMethodCollectorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testGetNodeTypeAsksToBeCalledForEveryMethodBody(): void
    {
        self::assertSame(InClassMethodNode::class, (new InClassMethodCollector())->getNodeType());
    }

    #[DataProvider('providerRelationsWrittenInAMethodBody')]
    public function testProcessNodeReportsWhatAMethodBodyReaches(string $expected): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            __DIR__.'/../../../../Fixture/Source/MethodBody.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains($expected, CollectorRun::edgeDescriptions($collected));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRelationsWrittenInAMethodBody(): iterable
    {
        yield 'a class it instantiates' => ['Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass'];

        yield 'a static method it calls' => ['Tests\Fixture\Source\MethodBodyClass::testMethod -[static-call]-> DateTime::createFromFormat'];

        yield 'a class constant it reads' => ['Tests\Fixture\Source\MethodBodyClass::testMethod -[const-fetch]-> DateTime::ATOM'];
    }

    #[DataProvider('providerUsagesResolvableFromTheWrittenName')]
    public function testProcessNodeReportsAUsageWhoseOwnerIsWrittenOut(string $expected): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            __DIR__.'/../../../../Fixture/Source/UsageProcessors.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains($expected, CollectorRun::edgeDescriptions($collected));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUsagesResolvableFromTheWrittenName(): iterable
    {
        yield 'a function call' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[function-call]-> Tests\Fixture\Source\usage_target_func'];

        yield 'a call on $this' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageProcessorFixture::helperMethod'];

        yield 'a property read on $this' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageProcessorFixture::myProp'];

        yield 'a static property read' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[static-property-access]-> Tests\Fixture\Source\UsageDep::staticCount'];
    }

    #[DataProvider('providerUsagesNeedingAnInferredReceiver')]
    public function testProcessNodeLeavesOutAUsageWhoseOwnerIsOnlyInferable(string $unexpected): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            __DIR__.'/../../../../Fixture/Source/UsageProcessors.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains($unexpected, CollectorRun::edgeDescriptions($collected));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUsagesNeedingAnInferredReceiver(): iterable
    {
        yield 'a call on a local variable' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageDep::depMethod'];

        yield 'a property read on a local variable' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageDep::instanceProp'];
    }

    public function testProcessNodeReportsNothingForAFileWithNoMethodBody(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            __DIR__.'/../../../../Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertSame([], $collected);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerCompleteOutputOfEverySample')]
    public function testProcessNodeReportsExactlyTheseRelations(string $fixture, array $expected): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
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
        yield 'every kind of declaration' => ['Comprehensive', []];

        yield 'what a method body reaches' => ['MethodBody', [
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[const-fetch]-> DateTime::ATOM',
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass',
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[static-call]-> DateTime::createFromFormat',
        ]];

        yield 'every kind of usage' => ['UsageProcessors', [
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[function-call]-> Tests\Fixture\Source\usage_target_func',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[instantiation]-> Tests\Fixture\Source\UsageDep',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageProcessorFixture::helperMethod',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageProcessorFixture::myProp',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[static-property-access]-> Tests\Fixture\Source\UsageDep::staticCount',
        ]];

        yield 'guards and declared types' => ['Guards', [
            'Tests\Fixture\Source\GuardedClass::guarded -[catch]-> RuntimeException',
            'Tests\Fixture\Source\GuardedClass::guarded -[instanceof]-> Tests\Fixture\Source\GuardedClass',
            'Tests\Fixture\Source\GuardedClass::guardedBy -[instanceof]-> Tests\Fixture\Source\Guard',
        ]];

        yield 'a class that declares nothing' => ['ClassDependency', []];
    }

    public function testProcessNodeReportsEverySymbolItReadAsAnalysed(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
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
