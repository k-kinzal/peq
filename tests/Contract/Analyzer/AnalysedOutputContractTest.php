<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class AnalysedOutputContractTest extends TestCase
{
    /**
     * @param string       $fixture  The sample source to analyse
     * @param list<string> $expected Every symbol it declares
     */
    #[DataProvider('providerSymbolsOfEverySample')]
    public function testTheAnalysisFindsExactlyTheSymbolsTheSourceDeclares(string $fixture, array $expected): void
    {
        $graph = (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 2).'/Fixture/Source/'.$fixture.'.php');
        $symbols = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
        sort($symbols);

        self::assertSame($expected, $symbols);
    }

    /**
     * @return iterable<string, array{string, list<string>}> One case per sample
     */
    public static function providerSymbolsOfEverySample(): iterable
    {
        yield 'every kind of declaration' => ['Comprehensive', [
            'Attribute',
            'Tests\Fixture\Source\ComprehensiveClass',
            'Tests\Fixture\Source\ComprehensiveClass::MY_CONST',
            'Tests\Fixture\Source\ComprehensiveClass::__construct',
            'Tests\Fixture\Source\ComprehensiveClass::myMethod',
            'Tests\Fixture\Source\ComprehensiveClass::myProp',
            'Tests\Fixture\Source\ComprehensiveClass::promotedProp',
            'Tests\Fixture\Source\MyAttribute',
            'Tests\Fixture\Source\MyEnum',
            'Tests\Fixture\Source\MyEnum::A',
            'Tests\Fixture\Source\MyInterface',
            'Tests\Fixture\Source\MyTrait',
        ]];

        yield 'what a method body reaches' => ['MethodBody', [
            'DateTime',
            'DateTime::ATOM',
            'DateTime::createFromFormat',
            'Tests\Fixture\Source\MethodBodyClass',
            'Tests\Fixture\Source\MethodBodyClass::testMethod',
            'stdClass',
        ]];

        yield 'every kind of usage' => ['UsageProcessors', [
            'Tests\Fixture\Source\UsageDep',
            'Tests\Fixture\Source\UsageDep::depMethod',
            'Tests\Fixture\Source\UsageDep::instanceProp',
            'Tests\Fixture\Source\UsageDep::staticCount',
            'Tests\Fixture\Source\UsageProcessorFixture',
            'Tests\Fixture\Source\UsageProcessorFixture::helperMethod',
            'Tests\Fixture\Source\UsageProcessorFixture::myProp',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod',
            'Tests\Fixture\Source\usage_target_func',
        ]];

        yield 'guards and declared types' => ['Guards', [
            'RuntimeException',
            'Tests\Fixture\Source\Guard',
            'Tests\Fixture\Source\GuardedClass',
            'Tests\Fixture\Source\GuardedClass::guarded',
            'Tests\Fixture\Source\GuardedClass::guardedBy',
        ]];

        yield 'inheritance in every shape' => ['Inheritance', [
            'Tests\Fixture\Source\InheritanceAuditable',
            'Tests\Fixture\Source\InheritanceDocument',
            'Tests\Fixture\Source\InheritanceDocument::title',
            'Tests\Fixture\Source\InheritanceInvoice',
            'Tests\Fixture\Source\InheritanceInvoice::amount',
            'Tests\Fixture\Source\InheritanceInvoice::auditedBy',
            'Tests\Fixture\Source\InheritanceInvoice::createdAt',
            'Tests\Fixture\Source\InheritanceInvoice::refund',
            'Tests\Fixture\Source\InheritanceInvoice::title',
            'Tests\Fixture\Source\InheritancePayable',
            'Tests\Fixture\Source\InheritancePayable::amount',
            'Tests\Fixture\Source\InheritanceRefundable',
            'Tests\Fixture\Source\InheritanceRefundable::refund',
            'Tests\Fixture\Source\InheritanceStatus',
            'Tests\Fixture\Source\InheritanceStatus::Open',
            'Tests\Fixture\Source\InheritanceStatus::Paid',
            'Tests\Fixture\Source\InheritanceStatus::amount',
            'Tests\Fixture\Source\InheritanceTimestamped',
        ]];

        yield 'a class that declares nothing' => ['ClassDependency', [
            'Tests\Fixture\Source\ClassDependency',
        ]];
    }

    /**
     * @param string       $fixture  The sample source to analyse
     * @param list<string> $expected Every relation it writes
     */
    #[DataProvider('providerRelationsOfEverySample')]
    public function testTheAnalysisWritesExactlyTheRelationsTheSourceWrites(string $fixture, array $expected): void
    {
        $graph = (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 2).'/Fixture/Source/'.$fixture.'.php');
        $relations = array_map(static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString(), $graph->forwardEdges());
        sort($relations);

        self::assertSame($expected, $relations);
    }

    /**
     * @return iterable<string, array{string, list<string>}> One case per sample
     */
    public static function providerRelationsOfEverySample(): iterable
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
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[const-fetch]-> DateTime::ATOM',
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass',
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[phpdoc]-> DateTime',
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[static-call]-> DateTime::createFromFormat',
        ]];

        yield 'every kind of usage' => ['UsageProcessors', [
            'Tests\Fixture\Source\UsageDep -[declaration-method]-> Tests\Fixture\Source\UsageDep::depMethod',
            'Tests\Fixture\Source\UsageDep -[declaration-property]-> Tests\Fixture\Source\UsageDep::instanceProp',
            'Tests\Fixture\Source\UsageDep -[declaration-property]-> Tests\Fixture\Source\UsageDep::staticCount',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-method]-> Tests\Fixture\Source\UsageProcessorFixture::helperMethod',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-method]-> Tests\Fixture\Source\UsageProcessorFixture::testMethod',
            'Tests\Fixture\Source\UsageProcessorFixture -[declaration-property]-> Tests\Fixture\Source\UsageProcessorFixture::myProp',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[function-call]-> Tests\Fixture\Source\usage_target_func',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[instantiation]-> Tests\Fixture\Source\UsageDep',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageDep::depMethod',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[method-call]-> Tests\Fixture\Source\UsageProcessorFixture::helperMethod',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageProcessorFixture::myProp',
            'Tests\Fixture\Source\UsageProcessorFixture::testMethod -[static-property-access]-> Tests\Fixture\Source\UsageDep::staticCount',
        ]];

        yield 'guards and declared types' => ['Guards', [
            'Tests\Fixture\Source\GuardedClass -[declaration-method]-> Tests\Fixture\Source\GuardedClass::guarded',
            'Tests\Fixture\Source\GuardedClass -[declaration-method]-> Tests\Fixture\Source\GuardedClass::guardedBy',
            'Tests\Fixture\Source\GuardedClass::guarded -[catch]-> RuntimeException',
            'Tests\Fixture\Source\GuardedClass::guarded -[instanceof]-> Tests\Fixture\Source\GuardedClass',
            'Tests\Fixture\Source\GuardedClass::guardedBy -[declaration-type-parameter]-> Tests\Fixture\Source\Guard',
            'Tests\Fixture\Source\GuardedClass::guardedBy -[declaration-type-return]-> Tests\Fixture\Source\GuardedClass',
            'Tests\Fixture\Source\GuardedClass::guardedBy -[instanceof]-> Tests\Fixture\Source\Guard',
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
}
