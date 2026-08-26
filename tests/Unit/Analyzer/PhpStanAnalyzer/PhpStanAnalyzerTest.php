<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Medium]
final class PhpStanAnalyzerTest extends TestCase
{
    public function testAnalyzeReadsTheSymbolsDeclaredUnderThePath(): void
    {
        $graph = (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 3).'/Fixture/Sample')
        ;

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    #[DataProvider('providerSymbolsTheSampleDeclares')]
    public function testAnalyzeRecordsEverySymbolTheSourcesDeclare(string $name, NodeKind $kind): void
    {
        $graph = (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 3).'/Fixture/Sample')
        ;

        self::assertSame($kind, $graph->nodeNamed($name)?->kind());
    }

    /**
     * @return iterable<string, array{string, NodeKind}>
     */
    public static function providerSymbolsTheSampleDeclares(): iterable
    {
        yield 'a class' => ['Tests\Fixture\Sample\ComplexClass', NodeKind::Klass];

        yield 'an interface' => ['Tests\Fixture\Sample\MyInterface', NodeKind::Interface];

        yield 'a trait' => ['Tests\Fixture\Sample\MyTrait', NodeKind::Trait];

        yield 'a method' => ['Tests\Fixture\Sample\ComplexClass::complexMethod', NodeKind::Method];

        yield 'an enum' => ['Tests\Fixture\Sample\MyEnum', NodeKind::Enum];

        yield 'an enum case' => ['Tests\Fixture\Sample\MyEnum::A', NodeKind::EnumCase];
    }

    /**
     * @throws RuntimeException
     */
    public function testAnalyzeRecordsWhatASignatureCommitsTo(): void
    {
        $graph = (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 3).'/Fixture/Sample')
        ;

        self::assertNotNull($graph->edge(
            $graph->nodeNamed('Tests\Fixture\Sample\ComplexClass')?->id() ?? throw new RuntimeException('missing class'),
            $graph->nodeNamed('Tests\Fixture\Sample\ComplexClass::complexMethod')?->id() ?? throw new RuntimeException('missing method'),
            EdgeKind::DeclarationMethod,
        ));
    }

    /**
     * @throws RuntimeException
     */
    public function testAnalyzeMakesEveryRelationReadableInBothDirections(): void
    {
        $graph = (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 3).'/Fixture/Sample')
        ;
        $method = $graph->nodeNamed('Tests\Fixture\Sample\ComplexClass::complexMethod')?->id() ?? throw new RuntimeException('missing method');

        self::assertSame(EdgeKind::DeclaredIn, $graph->edges($method)[0]->kind());
    }

    public function testAnalyzeAcceptsASingleFileAsWellAsADirectory(): void
    {
        $graph = (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php')
        ;

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\AnalysedSample'));
    }

    public function testAnalyzeProducesAnEmptyGraphForAPathWithNoSources(): void
    {
        $graph = (new PhpStanAnalyzer())
            ->analyze(__DIR__.'/nonexistent')
        ;

        self::assertSame([], $graph->nodes());
    }

    public function testAnalyzeLeavesOutWhatTheExcludePatternsFilter(): void
    {
        $graph = (new PhpStanAnalyzer([], ['Sample']))
            ->analyze(dirname(__DIR__, 3).'/Fixture')
        ;

        self::assertNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeReportsASymbolItReadAsResolved(): void
    {
        $graph = (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php');

        self::assertTrue($graph->nodeNamed('Tests\Fixture\Source\MethodBodyClass')?->resolved());
    }

    public function testAnalyzeReportsASymbolItOnlySawReferencedAsUnresolved(): void
    {
        $graph = (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php');

        self::assertFalse($graph->nodeNamed('stdClass')?->resolved());
    }

    public function testAnalyzeRecordsWhereADeclaredSymbolIsWritten(): void
    {
        $graph = (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php');

        self::assertStringEndsWith('MethodBody.php', $graph->nodeNamed('Tests\Fixture\Source\MethodBodyClass')?->meta()->path ?? '');
    }

    /**
     * @throws AnalysisFailedException
     */
    public function testCollectReadsWhatPeqsCollectorsReportedForTheFiles(): void
    {
        $files = [dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'];
        $container = (new ContainerFactory())->create($files);

        $report = (new PhpStanAnalyzer())->collect($container, $files);

        self::assertNotEmpty($report->symbols());
    }
}
