<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\TypeReferences;

/**
 * @internal
 */
#[CoversClass(TypeResolver::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Analyzer\PhpStanAnalyzer\Processor\TypeReference::class)]
#[Small]
final class TypeResolverTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerTypeReferencesAndTheNamesTheyMention')]
    public function testResolveNamesReadsEveryNameAWrittenTypeMentions(?Node $type, array $expected): void
    {
        self::assertSame($expected, array_map(static fn (Name $name): string => $name->toString(), TypeResolver::resolveNames($type)));
    }

    /**
     * @return iterable<string, array{null|Node, list<string>}>
     */
    public static function providerTypeReferencesAndTheNamesTheyMention(): iterable
    {
        yield 'no type at all' => [null, []];

        yield 'a single name' => [new Name('App\Domain\Money'), ['App\Domain\Money']];

        yield 'a builtin keyword' => [new Identifier('int'), []];

        yield 'a nullable name' => [new NullableType(new Name('App\Domain\Money')), ['App\Domain\Money']];

        yield 'a union' => [
            new UnionType([new Name('App\Domain\Money'), new Name('App\Domain\Invoice')]),
            ['App\Domain\Money', 'App\Domain\Invoice'],
        ];

        yield 'an intersection' => [
            new IntersectionType([new Name('App\Domain\Payable'), new Name('App\Domain\Refundable')]),
            ['App\Domain\Payable', 'App\Domain\Refundable'],
        ];

        yield 'a disjunctive normal form type' => [
            new UnionType([new IntersectionType([new Name('App\Domain\Payable'), new Name('App\Domain\Refundable')]), new Identifier('null')]),
            ['App\Domain\Payable', 'App\Domain\Refundable'],
        ];
    }

    public function testReferencesLeavesOutNamesPhpResolvesItself(): void
    {
        $references = TypeResolver::references(TypeReferences::at(4, new Identifier('int')), '/project/src/Invoice.php');

        self::assertSame([], $references);
    }

    public function testReferencesReportsTheClassLikeAWrittenTypeNames(): void
    {
        $references = TypeResolver::references(TypeReferences::at(4, new Name('App\Domain\Money')), '/project/src/Invoice.php');

        self::assertCount(1, $references);
        self::assertSame('App\Domain\Money', $references[0]->node->id()->toString());
    }

    public function testReferencesReportsWhereEachNameIsWritten(): void
    {
        $references = TypeResolver::references(TypeReferences::at(4, new Name('App\Domain\Money')), '/project/src/Invoice.php');

        self::assertSame('/project/src/Invoice.php', $references[0]->meta->path);
    }

    public function testReferencesReportsOneEntryPerNameOfAUnion(): void
    {
        $union = new UnionType([TypeReferences::at(4, new Name('App\Domain\Money')), TypeReferences::at(4, new Name('App\Domain\Invoice')), TypeReferences::at(4, new Identifier('null'))]);

        self::assertCount(2, TypeResolver::references($union, '/project/src/Invoice.php'));
    }

    public function testReferencesReportsNothingWhenNoTypeWasWritten(): void
    {
        self::assertSame([], TypeResolver::references(null, '/project/src/Invoice.php'));
    }
}
