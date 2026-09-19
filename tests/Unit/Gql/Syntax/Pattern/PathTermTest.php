<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathTerm::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(NodePattern::class)]
#[Small]
final class PathTermTest extends TestCase
{
    #[DataProvider('providerEveryKindOfTerm')]
    public function testEveryPieceOfAPathIsATermOfItsOwnKind(PathTerm $term, string $expected): void
    {
        self::assertSame($expected, $term::class);
    }

    /**
     * @return iterable<string, array{PathTerm, class-string}>
     */
    public static function providerEveryKindOfTerm(): iterable
    {
        yield 'what matches a symbol' => [new NodePattern('p'), NodePattern::class];

        yield 'what matches a relation' => [new EdgePattern(EdgeDirection::Along), EdgePattern::class];

        yield 'a parenthesised stretch of both' => [new GroupPattern([new NodePattern('p')]), GroupPattern::class];
    }
}
