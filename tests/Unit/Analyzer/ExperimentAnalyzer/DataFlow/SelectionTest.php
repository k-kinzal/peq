<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Selection;
use App\Analyzer\Graph\Direction;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class SelectionTest extends TestCase
{
    #[DataProvider('providerDirections')]
    public function testRootsSelectsTheFirstOrLastSourceOccurrence(Direction $direction, int $line, string $kind): void
    {
        $source = "<?php function f(int \$x) {\n\$x = 2;\nreturn \$x;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $roots = (new Selection())->roots($graph, null, '$x', null, $direction);
        self::assertCount(1, $roots);
        self::assertSame([$line, '$x', $kind], [$graph->nodes[$roots[0]]->line, $graph->nodes[$roots[0]]->variable, $graph->nodes[$roots[0]]->kind]);
    }

    /**
     * @return iterable<array{Direction, int, string}>
     */
    public static function providerDirections(): iterable
    {
        yield [Direction::UsedBy, 1, 'parameter'];

        yield [Direction::Uses, 3, 'read'];
    }

    public function testRootsKeepsAnExplicitLineAndColumnInsteadOfTheDefault(): void
    {
        $source = "<?php function f(int \$x) {\n\$x = \$x;\nreturn \$x;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $roots = (new Selection())->roots($graph, 2, 'x', 1, Direction::Uses);
        self::assertCount(1, $roots);
        self::assertSame('write', $graph->nodes[$roots[0]]->kind);
        self::assertSame(2, $graph->nodes[$roots[0]]->line);
        self::assertCount(2, (new Selection())->roots($graph, 2, 'x', null, Direction::Uses));
    }

    public function testRootsDoesNotSkipAnUnreachableLastOccurrence(): void
    {
        $source = "<?php function f(int \$x) {\nreturn 1;\n\$x = 2;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $roots = (new Selection())->roots($graph, null, 'x', null, Direction::Uses);
        self::assertCount(1, $roots);
        self::assertSame(3, $graph->nodes[$roots[0]]->line);
        self::assertSame('NOT_ANALYZED', $graph->issues[$roots[0]]->code);
    }

    #[DataProvider('providerMissing')]
    public function testRootsRejectsMissingOrUnspecifiedVariables(?string $variable, ?int $column): void
    {
        $this->expectException(InspectionException::class);
        (new Selection())->roots(new DependencyGraph('f', '/f.php', ''), null, $variable, $column, Direction::Uses);
    }

    /**
     * @return iterable<array{?string, ?int}>
     */
    public static function providerMissing(): iterable
    {
        yield [null, null];

        yield ['x', null];

        yield ['x', 9];
    }

    /**
     * @param string $body Written function body
     */
    #[DataProvider('providerFinalWrites')]
    public function testLastResultSelectsTheCompletedSelfAssignment(string $body): void
    {
        $source = "<?php function f(int \$x) {\n".$body."\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $roots = (new Selection())->roots($graph, null, 'x', null, Direction::Uses);
        self::assertCount(1, $roots);
        self::assertSame([2, 1, 'write'], [$graph->nodes[$roots[0]]->line, $graph->nodes[$roots[0]]->column, $graph->nodes[$roots[0]]->kind]);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerFinalWrites(): iterable
    {
        yield ['$x = $x + 1;'];

        yield ['$x += $x;'];

        yield ['$x = $x++;'];

        yield ['$x = true ? ($x = 1) : ($x = 2);'];
    }

    public function testOccurrencesExcludesAClosureParameterWithTheSameSpelling(): void
    {
        $source = "<?php function f(int \$x) {\n\$closure = function (int \$x) { return \$x; };\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $roots = (new Selection())->roots($graph, null, 'x', null, Direction::Uses);
        self::assertCount(1, $roots);
        self::assertSame(1, $graph->nodes[$roots[0]]->line);
        self::assertSame('parameter', $graph->nodes[$roots[0]]->kind);
    }

    public function testInScopeKeepsAnExplicitCaptureButExcludesItsDeferredBody(): void
    {
        $source = "<?php function f(int \$x) {\n\$closure = function () use (\$x) {\nreturn \$x;\n};\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $roots = (new Selection())->roots($graph, null, 'x', null, Direction::Uses);
        self::assertCount(1, $roots);
        self::assertSame(2, $graph->nodes[$roots[0]]->line);
        self::assertSame('syntax-Expr_Variable', $graph->nodes[$roots[0]]->kind);
    }
}
