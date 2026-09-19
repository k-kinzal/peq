<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\OutputFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OutputFormat::class)]
#[Small]
final class OutputFormatTest extends TestCase
{
    public function testSpellWritesTheFormatsTheWayTheCommandLineSeparatesThem(): void
    {
        self::assertSame('tree|json|dot|table|graph', OutputFormat::spell());
    }

    #[DataProvider('providerEveryFormat')]
    public function testSpellOffersEveryFormatThatExists(OutputFormat $format): void
    {
        self::assertContains($format->value, explode('|', OutputFormat::spell()));
    }

    /**
     * @return iterable<string, array{OutputFormat}>
     */
    public static function providerEveryFormat(): iterable
    {
        foreach (OutputFormat::cases() as $format) {
            yield $format->value => [$format];
        }
    }

    #[DataProvider('providerHowEachFormatIsWritten')]
    public function testTheFormatIsNamedByTheWordTheUserTypes(string $written, OutputFormat $expected): void
    {
        self::assertSame($expected, OutputFormat::from($written));
    }

    /**
     * @return iterable<string, array{string, OutputFormat}>
     */
    public static function providerHowEachFormatIsWritten(): iterable
    {
        yield 'an indented tree' => ['tree', OutputFormat::Tree];

        yield 'a JSON document' => ['json', OutputFormat::Json];

        yield 'a Graphviz digraph' => ['dot', OutputFormat::Dot];

        yield 'a table of rows' => ['table', OutputFormat::Table];

        yield 'a drawing of the graph' => ['graph', OutputFormat::Graph];
    }

    public function testTheTreeIsTheFormatAReportIsWrittenInUnlessAnotherIsAsked(): void
    {
        self::assertSame(OutputFormat::Tree, OutputFormat::cases()[0]);
    }
}
