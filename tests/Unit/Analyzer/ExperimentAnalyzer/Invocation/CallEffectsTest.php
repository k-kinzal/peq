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
        $inputs = array_values(array_filter($graph->edges, static fn (\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency $edge): bool => $edge->from === $writes[0]->id && $edge->kind === 'data'));
        self::assertCount(1, $inputs);
        self::assertSame('call', $graph->nodes[$inputs[0]->to]->kind);

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

    /**
     * @param list<null|string> $reads
     * @param list<null|string> $writes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCallArguments')]
    public function testApplyDistinguishesNamedOutputsFromOrdinaryArguments(string $call, array $reads, array $writes): void
    {
        $source = '<?php function f($text, $output, $args) { '.$call.'; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame($reads, array_values(array_map(static fn (Occurrence $node): ?string => $node->variable, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'read'))));
        self::assertSame($writes, array_values(array_map(static fn (Occurrence $node): ?string => $node->variable, array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->kind === 'call-write'))));

    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function providerCallArguments(): iterable
    {
        yield 'named regex output' => ['PREG_MATCH(matches: $output, subject: $text, pattern: "/x/")', ['$text'], ['$output']];

        yield 'ordinary input' => ['unknown_function($output)', ['$output'], []];

        yield 'unpacked inputs' => ['preg_match(...$args)', ['$args'], []];

        yield 'callable creation' => ['preg_match(...)', [], []];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCallNames')]
    public function testNameResolvesOnlyStaticallyKnownReceivers(string $expression, ?string $expected): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$expression.';');
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        $node = $parsed[0]->expr;
        self::assertTrue($node instanceof \PhpParser\Node\Expr\FuncCall || $node instanceof \PhpParser\Node\Expr\MethodCall || $node instanceof \PhpParser\Node\Expr\New_ || $node instanceof StaticCall);
        self::assertSame($expected, (new CallEffects([], 'Example'))->name($node));
    }

    /**
     * @return iterable<string, array{string, null|string}>
     */
    public static function providerCallNames(): iterable
    {
        yield 'constructor' => ['new Other()', 'Other::__construct'];

        yield 'self constructor' => ['new self()', 'Example::__construct'];

        yield 'parent constructor' => ['new PARENT()', null];

        yield 'parent static call' => ['PARENT::replace()', null];

        yield 'this call' => ['$this->replace()', 'Example::replace'];

        yield 'other receiver' => ['$other->replace()', null];

        yield 'dynamic method' => ['$this->$method()', null];

        yield 'dynamic function' => ['$function()', null];
    }

    public function testSignatureFindsNamespacedFunctionsCaseInsensitively(): void
    {
        $name = new Name('Replace', ['namespacedName' => new Name('Example\Replace')]);
        $effects = new CallEffects(['example\replace' => [true]]);
        self::assertSame([true], $effects->signature(new \PhpParser\Node\Expr\FuncCall($name)));
    }
}
