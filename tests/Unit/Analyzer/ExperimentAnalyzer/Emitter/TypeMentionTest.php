<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Emitter;

use App\Analyzer\ExperimentAnalyzer\Emitter\TypeMention;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\QualifiedName;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TypeMention::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(QualifiedName::class)]
#[Small]
final class TypeMentionTest extends TestCase
{
    /**
     * @param list<string> $expected The names the type is expected to mention
     */
    #[DataProvider('providerWrittenTypes')]
    public function testNamesOfReadsTheClassLikesAWrittenTypeNames(string $code, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $property = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($property);

        self::assertSame($expected, array_map(static fn (Name $name): string => $name->toString(), TypeMention::namesOf($property->type)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerWrittenTypes(): iterable
    {
        yield 'a single name' => ["<?php\nclass Written { public \\App\\Money \$held; }\n", ['App\Money']];

        yield 'a nullable name' => ["<?php\nclass Written { public ?\\App\\Money \$held; }\n", ['App\Money']];

        yield 'a union' => ["<?php\nclass Written { public \\App\\Money|\\App\\Rate \$held; }\n", ['App\Money', 'App\Rate']];

        yield 'an intersection' => ["<?php\nclass Written { public \\App\\Money&\\Countable \$held; }\n", ['App\Money', 'Countable']];

        yield 'a union of intersections' => ["<?php\nclass Written { public (\\App\\Money&\\Countable)|null \$held; }\n", ['App\Money', 'Countable']];

        yield 'a builtin type names nothing' => ["<?php\nclass Written { public int \$held; }\n", []];
    }

    public function testNamesOfReadsNothingWhereNoTypeIsWritten(): void
    {
        self::assertSame([], TypeMention::namesOf(null));
    }

    public function testOfMentionsNothingForATypeThatNamesNoClassLike(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Written { public int \$held; }\n") ?? []);
        $property = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($property);

        self::assertSame([], TypeMention::of($property->type, '/project/Invoice.php'));
    }

    public function testOfMentionsTheClassLikeAWrittenTypeNamesWhereItIsWritten(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Written {\n    public \\App\\Money \$held;\n}\n") ?? []);
        $property = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($property);

        self::assertEquals(
            [new TypeMention(new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('/project/Invoice.php', 3, 1))],
            TypeMention::of($property->type, '/project/Invoice.php'),
        );
    }

    public function testOfLeavesOutTheBuiltinPartsOfAWrittenType(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Written { public \\App\\Money|null \$held; }\n") ?? []);
        $property = (new NodeFinder())->findFirstInstanceOf($parsed, Property::class);
        self::assertNotNull($property);

        self::assertEquals(
            [new TypeMention(new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('/project/Invoice.php', 2, 1))],
            TypeMention::of($property->type, '/project/Invoice.php'),
        );
    }
}
