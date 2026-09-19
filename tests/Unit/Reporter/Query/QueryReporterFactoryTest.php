<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\OutputFormat;
use App\Config\RawConfig;
use App\Gql\Element\ElementGraph;
use App\Reporter\Diagram\DiagramRenderer;
use App\Reporter\Query\DiagramWriter;
use App\Reporter\Query\DotWriter;
use App\Reporter\Query\JsonWriter;
use App\Reporter\Query\QueryReporterFactory;
use App\Reporter\Query\TableWriter;
use App\Reporter\Query\TreeWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryReporterFactory::class)]
#[UsesClass(Config::class)]
#[UsesClass(OutputFormat::class)]
#[UsesClass(AnalyzerKind::class)]
#[UsesClass(ConfigException::class)]
#[UsesClass(RawConfig::class)]
#[UsesClass(Direction::class)]
#[UsesClass(ElementGraph::class)]
#[UsesClass(DiagramWriter::class)]
#[UsesClass(DotWriter::class)]
#[UsesClass(JsonWriter::class)]
#[UsesClass(TableWriter::class)]
#[UsesClass(TreeWriter::class)]
#[UsesClass(DiagramRenderer::class)]
#[Small]
final class QueryReporterFactoryTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    #[DataProvider('providerFormatsAndTheReporterTheyAskFor')]
    public function testCreateBuildsTheReporterTheConfigurationAsksFor(string $format, string $expected): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => $format]);

        self::assertSame($expected, (new QueryReporterFactory())->create($config)::class);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFormatsAndTheReporterTheyAskFor(): iterable
    {
        yield 'a table to scan' => ['table', TableWriter::class];

        yield 'fields a program can address' => ['json', JsonWriter::class];

        yield 'a drawing to look at' => ['graph', DiagramWriter::class];

        yield 'a digraph for a renderer' => ['dot', DotWriter::class];

        yield 'a tree of the paths it bound' => ['tree', TreeWriter::class];
    }

    /**
     * @throws ConfigException
     */
    public function testCreateGivesTheGraphToTheFormatsThatDrawTheAnswer(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'graph']);

        self::assertSame(
            DiagramWriter::class,
            (new QueryReporterFactory())->create($config, new ElementGraph([], [], []))::class,
        );
    }
}
