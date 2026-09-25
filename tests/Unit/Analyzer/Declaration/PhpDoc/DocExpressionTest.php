<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocExpression;
use App\Analyzer\Declaration\PhpDoc\DocIndex;
use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocExpression::class)]
#[UsesNamespace('App\Analyzer\Declaration\PhpDoc')]
#[Small]
final class DocExpressionTest extends TestCase
{
    #[DataProvider('providerValues')]
    public function testObjectsAndElementProjectDifferentLevels(string $written, string $objects, ?string $element): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace(new Name('App'));
        $type = (new DocParser())->parse('/** @return '.$written.' */')->getReturnTagValues()[0]->type;
        $value = new DocExpression($type, new DocScope($names, 'App\Subject'), new DocIndex());

        self::assertSame($objects, $value->objects());
        self::assertSame($element, $value->element()?->objects());
        self::assertSame('App\Other', $value->nested(new IdentifierTypeNode('Other'))->objects());
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function providerValues(): iterable
    {
        yield 'class' => ['Item', 'App\Item', null];

        yield 'nullable' => ['?Item', 'App\Item', null];

        yield 'union' => ['Item|Other|null', 'App\Item|App\Other', null];

        yield 'intersection' => ['Item&Other', 'App\Item&App\Other', null];

        yield 'generic object' => ['Box<Item>', 'App\Box', null];

        yield 'class string' => ['class-string<Item>', '', null];

        yield 'list' => ['list<Item>', '', 'App\Item'];

        yield 'array' => ['array<string, Item>', '', 'App\Item'];

        yield 'suffix' => ['Item[]', '', 'App\Item'];

        yield 'shape' => ['array{item: Item, other: Other}', '', 'App\Item|App\Other'];

        yield 'callable' => ['callable(Item): Other', '', null];

        yield 'closure' => ['\Closure(Item): Other', 'Closure', null];

        yield 'conditional' => ['(Item is Other ? Item : Result)', 'App\Item|App\Result', null];

        yield 'parameter conditional' => ['($value is Item ? Item : Result)', 'App\Item|App\Result', null];

        yield 'offset' => ["array{item: Item}['item']", 'App\Item', null];

        yield 'this' => ['$this', 'App\Subject', null];
    }

    public function testUnionPreservesEachDeclarationsNamespace(): void
    {
        $firstNames = new NameContext(new Collecting());
        $firstNames->startNamespace(new Name('First'));
        $secondNames = new NameContext(new Collecting());
        $secondNames->startNamespace(new Name('Second'));
        $type = (new DocParser())->parse('/** @return list<Item> */')->getReturnTagValues()[0]->type;
        $first = new DocExpression($type, new DocScope($firstNames), new DocIndex());
        $second = new DocExpression($type, new DocScope($secondNames), new DocIndex());

        self::assertSame('First\Item|Second\Item', DocExpression::union([$first, $second])?->element()?->objects());
        self::assertNull(DocExpression::union([]));
        self::assertSame($first, DocExpression::union([$first]));
    }

    public function testIntersectionDistributesNestedUnions(): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace();
        $type = (new DocParser())->parse('/** @return (First|Second)&Contract */')->getReturnTagValues()[0]->type;
        self::assertInstanceOf(\PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode::class, $type);
        $value = new DocExpression($type, new DocScope($names), new DocIndex());

        self::assertSame('First&Contract|Second&Contract', $value->intersection($type));
    }

    public function testNestedRetainsTheDeclaringNamespace(): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace(new Name('App'));
        $value = new DocExpression(new IdentifierTypeNode('Item'), new DocScope($names), new DocIndex());

        self::assertSame('App\Other', $value->nested(new IdentifierTypeNode('Other'))->objects());
    }

    public function testShapeElementReadsUnsealedEntriesWithoutOverridingKnownKeys(): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace();
        $type = (new DocParser())->parse('/** @return array{known: First, ...<string, Other>} */')->getReturnTagValues()[0]->type;
        self::assertInstanceOf(\PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode::class, $type);
        $value = new DocExpression($type, new DocScope($names), new DocIndex());

        self::assertSame('First', $value->shapeElement($type, 'known')?->objects());
        self::assertSame('Other', $value->shapeElement($type, 'unknown')?->objects());
        self::assertSame('First|Other', $value->shapeElement($type, null)?->objects());
    }

    public function testElementSelectsOnlyTheRequestedShapeKey(): void
    {
        $type = (new DocParser())->parse('/** @return array{item: Item, other: Other} */')->getReturnTagValues()[0]->type;
        $names = new NameContext(new Collecting());
        $names->startNamespace();
        $value = new DocExpression($type, new DocScope($names), new DocIndex());

        self::assertSame('Item', $value->element('item')?->objects());
        self::assertNull($value->element('missing'));
    }
}
