<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPStan\Node\InClassMethodNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

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
final class InClassMethodCollectorTest extends TestCase
{
    public function testGetNodeTypeAsksToBeCalledForEveryMethodBody(): void
    {
        self::assertSame(InClassMethodNode::class, (new InClassMethodCollector())->getNodeType());
    }

    #[DataProvider('providerRelationsWrittenInAMethodBody')]
    public function testProcessNodeReportsWhatAMethodBodyReaches(string $expected): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/MethodBody.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains($expected, $relations);
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
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/UsageProcessors.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains($expected, $relations);
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
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/UsageProcessors.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains($unexpected, $relations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUsagesNeedingAnInferredReceiver(): iterable
    {
        yield 'a call on a local variable' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageDep::depMethod'];

        yield 'a property read on a local variable' => ['Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageDep::instanceProp'];
    }

    #[DataProvider('providerNullsafeUsages')]
    public function testProcessNodeReportsANullsafeUsageAsTheUsageItGuards(string $expected): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, "<?php\nnamespace Tests\\Contract\\Analyzer\\Nullsafe;\n\nclass Subject\n{\n    public int \$prop = 1;\n\n    public function helper(): void {}\n\n    public function testMethod(): void\n    {\n        \$this?->helper();\n        \$read = \$this?->prop;\n    }\n}\n");
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains($expected, $relations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNullsafeUsages(): iterable
    {
        yield 'a nullsafe call on $this' => ['Tests\Contract\Analyzer\Nullsafe\Subject::testMethod -[method-call]-> Tests\Contract\Analyzer\Nullsafe\Subject::helper'];

        yield 'a nullsafe property read on $this' => ['Tests\Contract\Analyzer\Nullsafe\Subject::testMethod -[property-access]-> Tests\Contract\Analyzer\Nullsafe\Subject::prop'];
    }

    public function testProcessNodeReportsNothingForAFileWithNoMethodBody(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/ClassDependency.php');

        self::assertSame([], $graph->nodes());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerCompleteOutputOfEverySample')]
    public function testProcessNodeReportsExactlyTheseRelations(string $fixture, array $expected): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/'.$fixture.'.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());
        sort($relations);

        self::assertSame($expected, $relations);
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
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[phpdoc]-> DateTime',
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
        $graph = (new PhpStanAnalyzer(collectors: [InClassMethodCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php');

        $declared = array_values(array_filter($graph->nodes(), static fn (Node $node): bool => $node->kind() !== NodeKind::Unknown));

        self::assertSame(
            array_map(static fn (Node $node): string => $node->id()->toString(), $declared),
            array_map(static fn (Node $node): string => $node->id()->toString(), array_values(array_filter($declared, static fn (Node $node): bool => $node->resolved()))),
        );
    }
}
