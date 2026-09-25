<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\Calls;

use App\Analyzer\CachedSyntax;
use App\Analyzer\Declaration\Calls\WrittenCalls;
use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\SourceParser;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WrittenCalls::class)]
#[UsesNamespace('App')]
#[Small]
final class WrittenCallsTest extends TestCase
{
    public function testCapturePreservesAliasesQuotesNamesUnpackingAndExpressionSpacing(): void
    {
        $source = <<<'PHP'
            <?php
            namespace Example;
            use Other\Config as Alias;
            use function Other\send as dispatch;
            dispatch($variable, "literal", Alias::MODE, (1 +  2), ...$args, mode: null);
            PHP;
        $syntax = CachedSyntax::parse($source, SourceParser::forVersion(80300));
        self::assertNotNull($syntax->statements);
        $call = (new NodeFinder())->findFirstInstanceOf($syntax->statements, FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        self::assertInstanceOf(\PhpParser\Node\Name::class, $call->name);
        self::assertSame('Other\send', $call->name->toString());
        self::assertSame('dispatch($variable, "literal", Alias::MODE, (1 +  2), ...$args, mode: null)', $call->getAttribute('peqExpression'));
        self::assertEquals([
            new CallArgument('$variable'),
            new CallArgument('"literal"', type: 'string'),
            new CallArgument('Alias::MODE'),
            new CallArgument('(1 +  2)'),
            new CallArgument('...$args', unpack: true),
            new CallArgument('mode: null', name: 'mode', type: 'null'),
        ], $call->getAttribute('peqCallArguments'));
        self::assertSame(1, $call->getAttribute('peqStartColumn'));
    }

    #[DataProvider('providerLiteralTypes')]
    public function testTypeIsReportedOnlyWhenKnownFromSyntax(string $expression, ?string $type): void
    {
        $nodes = SourceParser::forVersion(80300)->parse('<?php '.$expression.';');
        self::assertNotNull($nodes);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $nodes[0]);
        self::assertSame($type, WrittenCalls::type($nodes[0]->expr));
    }

    /**
     * @return iterable<string, array{string, null|string}>
     */
    public static function providerLiteralTypes(): iterable
    {
        yield 'integer' => ['42', 'int'];

        yield 'float' => ['1.5', 'float'];

        yield 'string' => ["'text'", 'string'];

        yield 'interpolation' => ['"hello $name"', 'string'];

        yield 'true' => ['TRUE', 'bool'];

        yield 'false' => ['false', 'bool'];

        yield 'null' => ['null', 'null'];

        yield 'array' => ['[$variable]', 'array'];

        yield 'constant' => ['MODE', null];

        yield 'class constant' => ['Config::MODE', null];

        yield 'variable' => ['$variable', null];

        yield 'expression' => ['1 + 2', null];
    }

    public function testCaptureDoesNotTreatTheFirstClassCallablePlaceholderAsAnArgument(): void
    {
        $source = "<?php\r\n\t/* 日本語 */ f(...); f();";
        $syntax = CachedSyntax::parse($source, SourceParser::forVersion(80300));
        self::assertNotNull($syntax->statements);
        $calls = (new NodeFinder())->findInstanceOf($syntax->statements, FuncCall::class);

        self::assertCount(2, $calls);
        self::assertSame('f(...)', $calls[0]->getAttribute('peqExpression'));
        self::assertSame([], $calls[0]->getAttribute('peqCallArguments'));
        self::assertSame([], $calls[1]->getAttribute('peqCallArguments'));
        self::assertSame(18, $calls[0]->getAttribute('peqStartColumn'));
        self::assertSame(26, $calls[1]->getAttribute('peqStartColumn'));
    }

    public function testTextUsesTheInclusiveSourceRange(): void
    {
        $node = new \PhpParser\Node\Scalar\Int_(42, ['startFilePos' => 2, 'endFilePos' => 3]);
        self::assertSame('42', WrittenCalls::text($node, 'x 42 z'));
    }
}
