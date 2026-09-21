<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class CapturesTest extends TestCase
{
    public function testReadDoesNotCaptureNestedParameters(): void
    {
        $source = <<<'SOURCE'
            <?php function f($a) { return fn ($x) => fn ($y) => $a + $x + $y; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $captures = array_values(array_map(static fn (Occurrence $node): ?string => $node->variable, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'capture')));
        self::assertSame(['$a'], $captures);
    }

    /**
     * @param list<array{null|string, string}> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCaptureDependencies')]
    public function testReadConnectsCapturesToTheirCreationTimeValues(string $body, array $expected): void
    {
        $source = '<?php class C { function f($a) { '.$body.' } }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Class_::class, $parsed[0]);
        $method = $parsed[0]->getMethod('f');
        self::assertNotNull($method);
        $graph = (new Inspection())->analyze($method, new DependencyGraph('C::f', '/f.php', $source));
        $inputs = array_values(array_map(
            static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): array => [$graph->nodes[$edge->from]->variable, $graph->nodes[$edge->to]->kind],
            array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => $graph->nodes[$edge->from]->kind === 'capture' && $edge->kind === 'data'),
        ));
        self::assertSame($expected, $inputs);
        self::assertStringContainsString('the nested body is not executed', implode('', $graph->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<array{string, string}>}>
     */
    public static function providerCaptureDependencies(): iterable
    {
        yield 'explicit value' => ['return function () use ($a) { return $a; };', [['$a', 'parameter']]];

        yield 'arrow excludes its parameter' => ['return fn ($x) => $a + $x;', [['$a', 'parameter']]];

        yield 'implicit receiver' => ['return function () { return $this->value; };', [['$this', 'receiver']]];

        yield 'static closure excludes receiver' => ['return static function () { return $this->value; };', []];
    }
}
