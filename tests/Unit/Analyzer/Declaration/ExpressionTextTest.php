<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration;

use App\Analyzer\Declaration\ExpressionText;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExpressionText::class)]
#[Small]
final class ExpressionTextTest extends TestCase
{
    #[DataProvider('providerExpressionsAsTheyAreWritten')]
    public function testOfWritesAnExpressionTheWayItWasWritten(Expr $expression, string $written): void
    {
        self::assertSame($written, ExpressionText::of($expression));
    }

    /**
     * @return iterable<string, array{Expr, string}>
     */
    public static function providerExpressionsAsTheyAreWritten(): iterable
    {
        yield 'a string' => [new String_('/users'), "'/users'"];

        yield 'an integer' => [new Int_(3), '3'];

        yield 'a list' => [
            new Array_([new ArrayItem(new String_('GET'))]),
            "['GET']",
        ];

        yield 'a constant nothing here could evaluate' => [
            new ClassConstFetch(new Name('App\Http\Method'), new Identifier('Get')),
            'App\Http\Method::Get',
        ];
    }

    public function testOfSaysNothingAboutADeclarationThatWroteNoExpression(): void
    {
        self::assertNull(ExpressionText::of(null));
    }

    #[DataProvider('providerArgumentsAsTheyAreWritten')]
    public function testArgumentWritesAnArgumentTheWayItWasGiven(Arg|VariadicPlaceholder $argument, string $written): void
    {
        self::assertSame($written, ExpressionText::argument($argument));
    }

    /**
     * @return iterable<string, array{Arg|VariadicPlaceholder, string}>
     */
    public static function providerArgumentsAsTheyAreWritten(): iterable
    {
        yield 'positional' => [new Arg(new String_('/users')), "'/users'"];

        yield 'named' => [new Arg(new String_('/users'), name: new Identifier('path')), "path: '/users'"];

        yield 'unpacked' => [new Arg(new String_('/users'), unpack: true), "...'/users'"];

        yield 'a first-class callable placeholder' => [new VariadicPlaceholder(), '...'];
    }
}
