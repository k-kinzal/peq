<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PhpParser\Node as PhpParserNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

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
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticCallProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticPropertyAccessProcessor::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\Processor\TypeReference::class)]
#[CoversClass(\App\Analyzer\PhpStanAnalyzer\SourceResolver::class)]
#[Medium]
final class DependencyCollectorTest extends TestCase
{
    public function testGetNodeTypeAsksToBeCalledForEverySyntaxNode(): void
    {
        self::assertSame(PhpParserNode::class, (new DependencyCollector())->getNodeType());
    }

    #[DataProvider('providerDeclaredSymbols')]
    public function testProcessNodeReportsEverySymbolADeclarationBringsIn(string $expected): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/Comprehensive.php');

        self::assertContains($expected, array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes()));
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
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/Comprehensive.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains($expected, $relations);
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
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/Comprehensive.php');

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass', array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes()));
    }

    public function testUsageIsLeftToTheOtherCollectorInsideAMethodBody(): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(__DIR__.'/../../../../Fixture/Source/MethodBody.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertNotContains(
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass',
            $relations,
        );
    }

    #[DataProvider('providerUsagesWrittenInAFunctionBody')]
    public function testProcessNodeReportsWhatAFunctionBodyUses(string $expected): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, implode(PHP_EOL, [
            '<?php',
            'namespace Tests\Contract\Analyzer\Functions;',
            '',
            'class Holder',
            '{',
            '    public static int $count = 0;',
            '}',
            '',
            'function build(): void',
            '{',
            '    $list = new \ArrayObject();',
            '    \DateTime::createFromFormat(\'Y\', \'2024\');',
            '    $format = \DateTime::ATOM;',
            '    try {',
            '        $list->count();',
            '    } catch (\RuntimeException $failure) {',
            '    }',
            '    $countable = $list instanceof \Countable;',
            '    \Elsewhere\helper();',
            '    $count = Holder::$count;',
            '}',
            '',
        ]));
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains($expected, $relations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUsagesWrittenInAFunctionBody(): iterable
    {
        yield 'a class it instantiates' => ['Tests\Contract\Analyzer\Functions\build -[instantiation]-> ArrayObject'];

        yield 'a static method it calls' => ['Tests\Contract\Analyzer\Functions\build -[static-call]-> DateTime::createFromFormat'];

        yield 'a class constant it reads' => ['Tests\Contract\Analyzer\Functions\build -[const-fetch]-> DateTime::ATOM'];

        yield 'an exception it catches' => ['Tests\Contract\Analyzer\Functions\build -[catch]-> RuntimeException'];

        yield 'a type it checks against' => ['Tests\Contract\Analyzer\Functions\build -[instanceof]-> Countable'];

        yield 'a function it calls' => ['Tests\Contract\Analyzer\Functions\build -[function-call]-> Elsewhere\helper'];

        yield 'a static property it reads' => ['Tests\Contract\Analyzer\Functions\build -[static-property-access]-> Tests\Contract\Analyzer\Functions\Holder::count'];
    }

    public function testUsageIsReportedInsideAFunctionBody(): void
    {
        $file = sys_get_temp_dir().'/'.uniqid('peq-snippet-', true).'.php';
        file_put_contents($file, "<?php\nnamespace Tests\\Contract\\Analyzer\\Functions;\n\nfunction build(): object\n{\n    return new \\stdClass();\n}\n");
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze($file);
        unlink($file);
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());

        self::assertContains('Tests\Contract\Analyzer\Functions\build -[instantiation]-> stdClass', $relations);
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerCompleteOutputOfEverySample')]
    public function testProcessNodeReportsExactlyTheseRelations(string $fixture, array $expected): void
    {
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/'.$fixture.'.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());
        sort($relations);

        self::assertSame($expected, $relations);
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
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[phpdoc]-> DateTime',
        ]];

        yield 'every kind of usage' => ['UsageProcessors', [
            'Tests\Fixture\Source\UsageDep -[declaration-method]-> Tests\Fixture\Source\UsageDep::depMethod',
            'Tests\Fixture\Source\UsageDep -[declaration-property]-> Tests\Fixture\Source\UsageDep::instanceProp',
            'Tests\Fixture\Source\UsageDep -[declaration-property]-> Tests\Fixture\Source\UsageDep::staticCount',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-method]-> Tests\Fixture\Source\UsageProcessorFixture::helperMethod',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-method]-> Tests\Fixture\Source\UsageProcessorFixture::testMethod',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-property]-> Tests\Fixture\Source\UsageProcessorFixture::myProp',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageDep::depMethod',
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
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-method]-> Tests\Fixture\Source\InheritanceInvoice::auditedBy',
            'Tests\Fixture\Source\InheritanceInvoice -[declaration-method]-> Tests\Fixture\Source\InheritanceInvoice::createdAt',
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
        $graph = (new PhpStanAnalyzer(collectors: [DependencyCollector::class]))->analyze(dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php');

        $declared = array_values(array_filter($graph->nodes(), static fn (Node $node): bool => $node->kind() !== NodeKind::Unknown));

        self::assertSame(
            array_map(static fn (Node $node): string => $node->id()->toString(), $declared),
            array_map(static fn (Node $node): string => $node->id()->toString(), array_values(array_filter($declared, static fn (Node $node): bool => $node->resolved()))),
        );
    }
}
