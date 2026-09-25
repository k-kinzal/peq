<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NativeAnalyzer::class)]
#[Medium]
final class NativeAnalyzerTest extends TestCase
{
    public function testBuildOfAnEmptySelectionHasNoSymbols(): void
    {
        self::assertSame([], (new NativeAnalyzer())->build([], '')->nodes());
    }

    public function testAnalyzeReadsTheSymbolsDeclaredUnderThePath(): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample');

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    #[DataProvider('providerSymbolsTheSampleDeclares')]
    public function testAnalyzeRecordsEverySymbolTheSourcesDeclare(string $name, NodeKind $kind): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample');

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

    #[DataProvider('providerRelationsTheSampleWrites')]
    public function testAnalyzeRecordsEveryRelationTheSourcesWrite(string $from, string $to, EdgeKind $kind): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample');
        $node = $graph->nodeNamed($from);
        self::assertNotNull($node);
        $target = $graph->nodeNamed($to);
        self::assertNotNull($target);

        self::assertSame($kind, $graph->edge($node->id(), $target->id(), $kind)?->kind());
    }

    /**
     * @return iterable<string, array{string, string, EdgeKind}>
     */
    public static function providerRelationsTheSampleWrites(): iterable
    {
        yield 'a class declares its methods' => ['Tests\Fixture\Sample\ComplexClass', 'Tests\Fixture\Sample\ComplexClass::complexMethod', EdgeKind::DeclarationMethod];

        yield 'an enum declares its cases' => ['Tests\Fixture\Sample\MyEnum', 'Tests\Fixture\Sample\MyEnum::A', EdgeKind::DeclarationEnumCase];

        yield 'a method is declared in its class' => ['Tests\Fixture\Sample\ComplexClass::complexMethod', 'Tests\Fixture\Sample\ComplexClass', EdgeKind::DeclaredIn];
    }

    public function testAnalyzeOfAPathWithNoPhpFileDescribesAnEmptyGraph(): void
    {
        self::assertSame([], (new NativeAnalyzer())->analyze(dirname(__DIR__, 4).'/build')->nodes());
    }

    public function testAnalyzeOfAPathThatDoesNotExistDescribesAnEmptyGraph(): void
    {
        self::assertSame([], (new NativeAnalyzer())->analyze('/nowhere/at/all')->nodes());
    }

    public function testAnalyzeReadsOnlyTheFilesTheIncludePatternsSelect(): void
    {
        $graph = (new NativeAnalyzer(includes: ['Analyzer']))->analyze(dirname(__DIR__, 3).'/Fixture');

        self::assertNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeLeavesOutTheFilesTheExcludePatternsFilter(): void
    {
        $graph = (new NativeAnalyzer(excludes: ['Sample']))->analyze(dirname(__DIR__, 3).'/Fixture');

        self::assertNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }

    public function testAnalyzeReadsASingleFileAsWellAsADirectory(): void
    {
        $graph = (new NativeAnalyzer())->analyze(dirname(__DIR__, 3).'/Fixture/Sample/ComplexSample.php');

        self::assertNotNull($graph->nodeNamed('Tests\Fixture\Sample\ComplexClass'));
    }
}
