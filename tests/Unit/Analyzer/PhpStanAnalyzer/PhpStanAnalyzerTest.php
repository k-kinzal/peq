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
use Tests\Double\FailingCollector;

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
        $graph = (new PhpStanAnalyzer(['Sample', 'Source'], ['Sample']))
            ->analyze(dirname(__DIR__, 3).'/Fixture')
        ;

        self::assertNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeKeepsWhatNoExcludePatternFilters(): void
    {
        $graph = (new PhpStanAnalyzer(['Sample', 'Source']))
            ->analyze(dirname(__DIR__, 3).'/Fixture')
        ;

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeReportsASymbolItReadAsResolved(): void
    {
        $graph = (new PhpStanAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php');

        self::assertTrue($graph->nodeNamed('Tests\Fixture\Source\MethodBodyClass')?->resolved());
    }

    public function testAnalyzeReportsAFailureRatherThanAGraphMissingTheFile(): void
    {
        $this->expectException(AnalysisFailedException::class);
        $this->expectExceptionMessageMatches('/^PHPStan could not finish analysing .*MethodBody\.php as the PHP version peq runs on: the collector gave up on a /');

        (new PhpStanAnalyzer(collectors: [FailingCollector::class]))->analyze(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php');
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
        $container = (new ContainerFactory())->create($files, [\App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector::class]);

        $report = (new PhpStanAnalyzer(collectors: [\App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector::class]))->collect($container, $files);

        self::assertNotSame([], $report->symbols());
    }

    public function testAnalysedVersionNamesTheVersionTheSourcesAreReadAs(): void
    {
        self::assertSame('PHP 7.1', (new PhpStanAnalyzer(phpVersion: 70100))->analysedVersion());
    }

    public function testAnalysedVersionNamesAPatchReleaseByTheMinorItBelongsTo(): void
    {
        self::assertSame('PHP 8.3', (new PhpStanAnalyzer(phpVersion: 80302))->analysedVersion());
    }

    public function testAnalysedVersionNamesTheRuntimeWhenNoVersionWasChosen(): void
    {
        self::assertSame('the PHP version peq runs on', (new PhpStanAnalyzer())->analysedVersion());
    }

    public function testAnalyzeReadsASourceAsThePhpVersionItWasGiven(): void
    {
        $graph = (new PhpStanAnalyzer(phpVersion: 70100))->analyze(dirname(__DIR__, 3).'/Fixture/Target/Php71.php.inc');

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Target\Php71\Reader'));
    }

    public function testAnalyzeLeavesOutAFileThePhpVersionItWasGivenCannotRead(): void
    {
        $graph = (new PhpStanAnalyzer(phpVersion: 70100))->analyze(dirname(__DIR__, 3).'/Fixture/Target/Php81.php.inc');

        self::assertSame([], $graph->nodes());
    }
}

namespace Tests\Double;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use RuntimeException;

/**
 * A collector that gives up on the first node it is shown.
 *
 * PHPStan builds its collectors from class names written into a configuration file,
 * so the double that makes an analysis fail has to be a class with a name: neither an
 * anonymous class nor a PHPUnit stub has one PHPStan could be told. It is declared
 * beside the one test that needs it.
 *
 * @implements Collector<Node, null>
 *
 * @internal
 */
final class FailingCollector implements Collector
{
    /**
     * {@inheritdoc}
     */
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException Always, naming the node it gave up on
     */
    public function processNode(Node $node, Scope $scope): mixed
    {
        throw new RuntimeException('the collector gave up on a '.$node->getType());
    }
}
