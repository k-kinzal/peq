<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Invocation;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;
use App\Analyzer\ExperimentAnalyzer\Invocation\CallEffects;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class CallEffectsTest extends TestCase
{
    public function testApplyRecordsBuiltinOutputParameters(): void
    {
        $source = <<<'SOURCE'
            <?php function f($text) { preg_match("/x/", $text, $matches); return $matches; }
            SOURCE;
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $writes = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'call-write'));
        self::assertCount(1, $writes);
        self::assertSame('$matches', $writes[0]->variable);
    }

    public function testSignaturePrefersWrittenFunctionsOverBuiltinNames(): void
    {
        $effects = new CallEffects(['sort' => [false, 'value' => false]]);
        self::assertSame([false, 'value' => false], $effects->signature(new \PhpParser\Node\Expr\FuncCall(new Name('sort'))));
    }

    public function testNameResolvesTheCurrentClassForSelfCalls(): void
    {
        $effects = new CallEffects([], 'Example');
        self::assertSame('Example::replace', $effects->name(new StaticCall(new Name('self'), 'replace')));
    }

    public function testInputsDoesNotReadThePreviousRegexOutput(): void
    {
        $source = '<?php function f($text) { $matches = []; preg_match("/x/", $text, $matches); return $matches; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $reads = array_values(array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'read' && $node->variable === '$matches'));
        self::assertCount(1, $reads);
        self::assertSame(85, $reads[0]->column);
    }

    public function testNameDoesNotConfuseParentWithTheCurrentClass(): void
    {
        $effects = new CallEffects([], 'Example');
        self::assertNull($effects->name(new StaticCall(new Name('parent'), 'replace')));
    }

    public function testApplyIncludesEveryVariadicReferenceArgument(): void
    {
        $source = '<?php function f($a, $b) { replace(1, $a, $b); return $b; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $calls = new CallEffects(['replace' => [false, true, '...' => true]]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source), $calls);
        $writes = array_values(array_map(static fn (Occurrence $node): ?string => $node->variable, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'call-write')));
        self::assertSame(['$a', '$b'], $writes);
    }
}
