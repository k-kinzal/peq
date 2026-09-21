<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class SupportedSyntaxTest extends TestCase
{
    public function testCheckRejectsGlobalState(): void
    {
        $this->expectException(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class);
        (new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->check([new \PhpParser\Node\Stmt\Global_([new \PhpParser\Node\Expr\Variable('a')])]);
    }

    public function testNodeRejectsReferenceCaptures(): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function () use (&$a) {};');
        self::assertNotNull($parsed);
        $this->expectException(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class);
        (new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->node($parsed[0]);
    }

    public function testUnsupportedAcceptsOrdinaryAssignments(): void
    {
        self::assertNull((new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->unsupported(new \PhpParser\Node\Expr\Assign(new \PhpParser\Node\Expr\Variable('a'), new \PhpParser\Node\Scalar\Int_(1))));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerRejectedSyntax')]
    public function testCheckNamesTheUnsupportedSemanticsBeforeWalking(string $body, string $reason): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function f($a) { '.$body.' }');
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $parsed[0]);
        $this->expectException(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class);
        $this->expectExceptionMessage('Cannot inspect line 1: '.$reason.'.');
        (new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->check($parsed[0]->stmts);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRejectedSyntax(): iterable
    {
        yield 'array alias' => ['$b = [&$a];', 'reference aliases are not supported'];

        yield 'exception flow' => ['try {} finally {}', 'exception flow (try/catch/finally) is not supported'];

        yield 'goto' => ['goto done;', 'goto flow is not supported'];

        yield 'label' => ['done:', 'goto flow is not supported'];

        yield 'static storage' => ['static $b;', 'shared variable storage is not supported'];

        yield 'include' => ['include $a;', 'dynamic execution and suspension are not supported'];

        yield 'yield from' => ['yield from $a;', 'dynamic execution and suspension are not supported'];

        yield 'nullsafe method' => ['$a?->call();', 'nullsafe evaluation is not supported'];

        yield 'nullsafe property' => ['$a?->value;', 'nullsafe evaluation is not supported'];

        yield 'extract with flags' => ['EXTRACT($a, EXTR_SKIP);', 'calls that dynamically introduce local variables are not supported'];

        yield 'legacy parse_str' => ['parse_str($a);', 'calls that dynamically introduce local variables are not supported'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsolatedSyntax')]
    public function testNodeAllowsOutputParametersAndDoesNotInspectClosureBodies(string $source): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        $syntax = new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax();
        $syntax->node($parsed[0]);
        self::assertNull($syntax->unsupported($parsed[0]->expr));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerIsolatedSyntax(): iterable
    {
        yield 'explicit output' => ['parse_str($text, $output);'];

        yield 'multibyte explicit output' => ['mb_parse_str($text, $output);'];

        yield 'nested closure' => ['function () { global $a; };'];

        yield 'nested arrow' => ['fn () => eval($code);'];
    }
}
