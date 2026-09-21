<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration;

use App\Analyzer\Declaration\TypeText;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TypeText::class)]
#[Small]
final class TypeTextTest extends TestCase
{
    #[DataProvider('providerTypesAsTheyAreWritten')]
    public function testOfWritesATypeTheWayItsDeclarationWritesIt(Node $type, string $written): void
    {
        self::assertSame($written, TypeText::of($type));
    }

    /**
     * @return iterable<string, array{Node, string}>
     */
    public static function providerTypesAsTheyAreWritten(): iterable
    {
        yield 'a builtin' => [new Identifier('int'), 'int'];

        yield 'a name, as the file writes it' => [new Name('Invoice'), 'Invoice'];

        yield 'a qualified name' => [new Name('App\Domain\Invoice'), 'App\Domain\Invoice'];

        yield 'a nullable type' => [new NullableType(new Name('Invoice')), '?Invoice'];

        yield 'a union' => [new UnionType([new Identifier('int'), new Identifier('string')]), 'int|string'];

        yield 'an intersection' => [
            new IntersectionType([new Name('Countable'), new Name('Traversable')]),
            'Countable&Traversable',
        ];

        yield 'a union holding an intersection' => [
            new UnionType([
                new IntersectionType([new Name('Countable'), new Name('Traversable')]),
                new Identifier('null'),
            ]),
            '(Countable&Traversable)|null',
        ];
    }

    public function testOfSaysNothingAboutADeclarationThatWroteNoType(): void
    {
        self::assertNull(TypeText::of(null));
    }

    public function testOfSaysNothingAboutSyntaxThatIsNotAType(): void
    {
        self::assertNull(TypeText::of(new Node\Scalar\String_('int')));
    }

    public function testPartsWritesOutTheMembersOfAUnion(): void
    {
        $written = new UnionType([new Identifier('int'), new Identifier('string')]);

        self::assertSame(['int', 'string'], TypeText::parts($written));
    }

    public function testPartsParenthesisesAnIntersectionWrittenInsideAUnion(): void
    {
        $written = new UnionType([
            new IntersectionType([new Name('Countable'), new Name('Traversable')]),
            new Identifier('null'),
        ]);

        self::assertSame(['(Countable&Traversable)', 'null'], TypeText::parts($written));
    }
}
