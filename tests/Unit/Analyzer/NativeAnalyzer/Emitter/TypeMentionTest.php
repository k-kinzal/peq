<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\NativeAnalyzer\Emitter\TypeMention;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(TypeMention::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class TypeMentionTest extends TestCase
{
    /**
     * @param list<string> $expected The names the type is expected to mention
     */
    #[DataProvider('providerWrittenTypes')]
    public function testNamesOfReadsTheClassLikesAWrittenTypeNames(string $written, array $expected): void
    {
        self::assertSame($expected, array_map(static fn (Name $name): string => $name->toString(), TypeMention::namesOf(ParsedSnippet::writtenType($written))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerWrittenTypes(): iterable
    {
        yield 'a single name' => ['\App\Money', ['App\Money']];

        yield 'a nullable name' => ['?\App\Money', ['App\Money']];

        yield 'a union' => ['\App\Money|\App\Rate', ['App\Money', 'App\Rate']];

        yield 'an intersection' => ['\App\Money&\Countable', ['App\Money', 'Countable']];

        yield 'a union of intersections' => ['(\App\Money&\Countable)|null', ['App\Money', 'Countable']];

        yield 'a builtin type names nothing' => ['int', []];
    }

    public function testNamesOfReadsNothingWhereNoTypeIsWritten(): void
    {
        self::assertSame([], TypeMention::namesOf(null));
    }

    public function testOfMentionsNothingForATypeThatNamesNoClassLike(): void
    {
        self::assertSame([], TypeMention::of(ParsedSnippet::writtenType('int'), '/project/Invoice.php'));
    }

    public function testOfMentionsTheClassLikeAWrittenTypeNames(): void
    {
        $mentions = TypeMention::of(ParsedSnippet::writtenType('\App\Money'), '/project/Invoice.php');

        self::assertSame('App\Money', $mentions[0]->node->id()->toString());
    }

    public function testOfRemembersWhereTheNameIsWritten(): void
    {
        $mentions = TypeMention::of(ParsedSnippet::writtenType('\App\Money'), '/project/Invoice.php');

        self::assertSame(['/project/Invoice.php', 2], [$mentions[0]->meta->path, $mentions[0]->meta->line]);
    }

    public function testOfLeavesOutTheBuiltinPartsOfAWrittenType(): void
    {
        $mentions = TypeMention::of(ParsedSnippet::writtenType('\App\Money|null'), '/project/Invoice.php');

        self::assertCount(1, $mentions);
    }
}
