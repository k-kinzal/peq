<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TypeResolverTest extends TestCase
{
    #[Test]
    public function testNullReturnsEmpty(): void
    {
        self::assertSame([], TypeResolver::resolveNames(null));
    }

    #[Test]
    public function testIdentifierReturnsEmpty(): void
    {
        $id = new Identifier('int');
        self::assertSame([], TypeResolver::resolveNames($id));
    }

    #[Test]
    public function testSimpleNameReturnsSingleElement(): void
    {
        $name = new Name('App\Foo');
        $result = TypeResolver::resolveNames($name);

        self::assertCount(1, $result);
        self::assertSame('App\Foo', $result[0]->toString());
    }

    #[Test]
    public function testFullyQualifiedName(): void
    {
        $name = new Name\FullyQualified('App\Bar');
        $result = TypeResolver::resolveNames($name);

        self::assertCount(1, $result);
        self::assertSame('App\Bar', $result[0]->toString());
    }

    #[Test]
    public function testNullableTypeUnwraps(): void
    {
        $nullable = new NullableType(new Name('App\Foo'));
        $result = TypeResolver::resolveNames($nullable);

        self::assertCount(1, $result);
        self::assertSame('App\Foo', $result[0]->toString());
    }

    #[Test]
    public function testNullableBuiltinReturnsEmpty(): void
    {
        $nullable = new NullableType(new Identifier('int'));
        self::assertSame([], TypeResolver::resolveNames($nullable));
    }

    #[Test]
    public function testUnionTypeCollectsAllNames(): void
    {
        $union = new UnionType([
            new Name('App\Foo'),
            new Identifier('null'),
            new Name('App\Bar'),
        ]);
        $result = TypeResolver::resolveNames($union);

        self::assertCount(2, $result);
        $names = array_map(fn (Name $n) => $n->toString(), $result);
        self::assertSame(['App\Foo', 'App\Bar'], $names);
    }

    #[Test]
    public function testIntersectionTypeCollectsAllNames(): void
    {
        $intersection = new IntersectionType([
            new Name('App\Foo'),
            new Name('App\Bar'),
        ]);
        $result = TypeResolver::resolveNames($intersection);

        self::assertCount(2, $result);
        $names = array_map(fn (Name $n) => $n->toString(), $result);
        self::assertSame(['App\Foo', 'App\Bar'], $names);
    }

    #[Test]
    public function testDnfTypeNestedIntersectionInsideUnion(): void
    {
        // DNF: (A&B)|C
        $dnf = new UnionType([
            new IntersectionType([
                new Name('App\A'),
                new Name('App\B'),
            ]),
            new Name('App\C'),
        ]);
        $result = TypeResolver::resolveNames($dnf);

        self::assertCount(3, $result);
        $names = array_map(fn (Name $n) => $n->toString(), $result);
        self::assertSame(['App\A', 'App\B', 'App\C'], $names);
    }

    #[Test]
    public function testUnionOfOnlyBuiltinsReturnsEmpty(): void
    {
        $union = new UnionType([
            new Identifier('int'),
            new Identifier('string'),
            new Identifier('null'),
        ]);
        self::assertSame([], TypeResolver::resolveNames($union));
    }
}
