<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\MethodNodeId;
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
final class TargetPhpVersionContractTest extends TestCase
{
    /**
     * @param int          $phpVersion The version the source is analysed as
     * @param string       $fixture    The sample source to analyse
     * @param list<string> $expected   Every symbol it declares or names
     */
    #[DataProvider('providerEverySupportedTargetVersion')]
    public function testASourceWrittenForASupportedVersionIsAnalysedIntoTheSymbolsItNames(int $phpVersion, string $fixture, array $expected): void
    {
        $graph = (new PhpStanAnalyzer(phpVersion: $phpVersion))->analyze(dirname(__DIR__, 2).'/Fixture/Target/'.$fixture.'.php.inc');
        $symbols = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
        sort($symbols);

        self::assertSame($expected, $symbols);
    }

    /**
     * @return iterable<string, array{int, string, list<string>}> One case per supported version
     */
    public static function providerEverySupportedTargetVersion(): iterable
    {
        yield 'PHP 7.1: nullable types, a string offset, and a multi catch' => [70100, 'Php71', [
            'LogicException',
            'RuntimeException',
            'Tests\Fixture\Target\Php71\Reader',
            'Tests\Fixture\Target\Php71\Reader::MODE',
            'Tests\Fixture\Target\Php71\Reader::describe',
            'Tests\Fixture\Target\Php71\Reader::initial',
            'strtoupper',
        ]];

        yield 'PHP 7.2: the object type' => [70200, 'Php72', [
            'Tests\Fixture\Target\Php72\Wrapper',
            'Tests\Fixture\Target\Php72\Wrapper::describe',
            'Tests\Fixture\Target\Php72\Wrapper::wrap',
            'get_class',
        ]];

        yield 'PHP 7.3: an indented heredoc and a trailing comma in a call' => [70300, 'Php73', [
            'Tests\Fixture\Target\Php73\Report',
            'Tests\Fixture\Target\Php73\Report::heading',
            'Tests\Fixture\Target\Php73\Report::widest',
            'max',
            'strlen',
        ]];

        yield 'PHP 7.4: typed properties, an arrow function, and a spread' => [70400, 'Php74', [
            'Tests\Fixture\Target\Php74\Totals',
            'Tests\Fixture\Target\Php74\Totals::amounts',
            'Tests\Fixture\Target\Php74\Totals::cached',
            'Tests\Fixture\Target\Php74\Totals::total',
            'array_map',
            'array_sum',
        ]];

        yield 'PHP 8.0: promotion, a match, a nullsafe call, and a named argument' => [80000, 'Php80', [
            'Tests\Fixture\Target\Php80\Money',
            'Tests\Fixture\Target\Php80\Money::__construct',
            'Tests\Fixture\Target\Php80\Money::amount',
            'Tests\Fixture\Target\Php80\Money::label',
            'str_pad',
        ]];

        yield 'PHP 8.1: an enum, a readonly property, and a first class callable' => [80100, 'Php81', [
            'RuntimeException',
            'Tests\Fixture\Target\Php81\Card',
            'Tests\Fixture\Target\Php81\Card::__construct',
            'Tests\Fixture\Target\Php81\Card::isRed',
            'Tests\Fixture\Target\Php81\Card::shout',
            'Tests\Fixture\Target\Php81\Card::suit',
            'Tests\Fixture\Target\Php81\Suit',
            'Tests\Fixture\Target\Php81\Suit::Hearts',
            'Tests\Fixture\Target\Php81\Suit::Spades',
            'array_map',
            'strtoupper',
        ]];

        yield 'PHP 8.2: a readonly class, a disjunctive normal form type, and a constant in a trait' => [80200, 'Php82', [
            'Tests\Fixture\Target\Php82\Catalogue',
            'Tests\Fixture\Target\Php82\Catalogue::SEPARATOR',
            'Tests\Fixture\Target\Php82\Catalogue::__construct',
            'Tests\Fixture\Target\Php82\Catalogue::describe',
            'Tests\Fixture\Target\Php82\Catalogue::title',
            'Tests\Fixture\Target\Php82\Countable',
            'Tests\Fixture\Target\Php82\Countable::count',
            'Tests\Fixture\Target\Php82\Described',
            'Tests\Fixture\Target\Php82\Named',
            'Tests\Fixture\Target\Php82\Named::name',
        ]];

        yield 'PHP 8.3: typed constants, an override attribute, and a dynamic constant fetch' => [80300, 'Php83', [
            'Override',
            'Tests\Fixture\Target\Php83\Banner',
            'Tests\Fixture\Target\Php83\Banner::PREFIX',
            'Tests\Fixture\Target\Php83\Banner::WIDTH',
            'Tests\Fixture\Target\Php83\Banner::render',
            'Tests\Fixture\Target\Php83\Renderer',
            'Tests\Fixture\Target\Php83\Renderer::render',
            'str_pad',
        ]];

        yield 'PHP 8.4: a property hook, asymmetric visibility, and new without parentheses' => [80400, 'Php84', [
            'Tests\Fixture\Target\Php84\Slug',
            'Tests\Fixture\Target\Php84\Slug::of',
            'Tests\Fixture\Target\Php84\Slug::source',
            'Tests\Fixture\Target\Php84\Slug::value',
        ]];

        yield 'PHP 8.5: the pipe operator and a no discard attribute' => [80500, 'Php85', [
            'NoDiscard',
            'Tests\Fixture\Target\Php85\Pipeline',
            'Tests\Fixture\Target\Php85\Pipeline::greeting',
            'Tests\Fixture\Target\Php85\Pipeline::shout',
            'strtoupper',
            'trim',
        ]];
    }

    public function testTheBodyOfAMethodIsReadAsTheVersionTheSourceIsAnalysedAs(): void
    {
        $graph = (new PhpStanAnalyzer(phpVersion: 70100))->analyze(dirname(__DIR__, 2).'/Fixture/Target/Php71.php.inc');

        self::assertNotNull($graph->edge(
            MethodNodeId::of('Tests\Fixture\Target\Php71\Reader', 'describe'),
            MethodNodeId::of('Tests\Fixture\Target\Php71\Reader', 'initial'),
        ));
    }

    public function testASourceNewerThanTheVersionItIsAnalysedAsIsReportedRatherThanLeftOut(): void
    {
        $this->expectException(AnalysisFailedException::class);
        $this->expectExceptionMessageMatches('/Php81\.php\.inc as PHP 7\.1: Syntax error/');

        (new PhpStanAnalyzer(phpVersion: 70100))->analyze(dirname(__DIR__, 2).'/Fixture/Target/Php81.php.inc');
    }

    public function testASourceOlderThanTheVersionItIsAnalysedAsIsReportedRatherThanLeftOut(): void
    {
        $this->expectException(AnalysisFailedException::class);
        $this->expectExceptionMessageMatches('/Php71\.php\.inc as PHP 8\.3: Syntax error/');

        (new PhpStanAnalyzer(phpVersion: 80300))->analyze(dirname(__DIR__, 2).'/Fixture/Target/Php71.php.inc');
    }
}
