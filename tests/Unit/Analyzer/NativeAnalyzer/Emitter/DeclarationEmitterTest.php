<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\ImplementsEdge;
use App\Analyzer\Graph\Edge\Declaration\TraitUseEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\Emitter\DeclarationEmitter;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeclarationEmitter::class)]
#[Medium]
final class DeclarationEmitterTest extends TestCase
{
    public function testAttributeUsagesReadsAnAttributeUnderTheNameItResolvesTo(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nuse App\\Http\\Route;\n#[Route('/users')]\nclass Written {}\n") ?? []);
        $declaration = (new NodeFinder())->findFirstInstanceOf($parsed, ClassLike::class);
        self::assertNotNull($declaration);

        self::assertEquals(
            [new AttributeUsage('App\Http\Route', ["'/users'"])],
            DeclarationEmitter::attributeUsages($declaration->attrGroups, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Written.php')),
        );
    }

    public function testAttributeUsagesOfADeclarationCarryingNoneReadsNone(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Written {}\n") ?? []);
        $declaration = (new NodeFinder())->findFirstInstanceOf($parsed, ClassLike::class);
        self::assertNotNull($declaration);

        self::assertSame([], DeclarationEmitter::attributeUsages($declaration->attrGroups, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Written.php')));
    }

    /**
     * @param list<Edge|Node> $expected What the declaration is expected to record
     */
    #[DataProvider('providerDeclarations')]
    public function testEmitRecordsWhatADeclarationIsBuiltFrom(string $code, NodeKind $kind, array $expected): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse($code) ?? []);
        $declaration = (new NodeFinder())->findFirstInstanceOf($parsed, ClassLike::class);
        self::assertNotNull($declaration);
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Written.php');

        self::assertEquals($expected, DeclarationEmitter::emit($declaration, $kind, 'App\Written', $scope));
    }

    /**
     * @return iterable<string, array{string, NodeKind, list<Edge|Node>}>
     */
    public static function providerDeclarations(): iterable
    {
        $at = new FileMeta('/project/Written.php', 3, 1);
        $class = new ClassNode(ClassNodeId::of('App\Written'), true, $at, new SymbolDeclaration());
        $interface = new GraphInterfaceNode(InterfaceNodeId::of('App\Written'), true, $at, new SymbolDeclaration());

        yield 'a class' => ["<?php\nnamespace App;\nclass Written {}\n", NodeKind::Klass, [$class]];

        yield 'a class that extends another' => [
            "<?php\nnamespace App;\nclass Written extends \\App\\Record {}\n",
            NodeKind::Klass,
            [$class, new ExtendsEdge($class, new ClassNode(ClassNodeId::of('App\Record'), false, null), $at)],
        ];

        yield 'an interface that extends two others' => [
            "<?php\nnamespace App;\ninterface Written extends \\App\\Readable, \\Countable {}\n",
            NodeKind::Interface,
            [
                $interface,
                new ExtendsEdge($interface, new GraphInterfaceNode(InterfaceNodeId::of('App\Readable'), false, null), $at),
                new ExtendsEdge($interface, new GraphInterfaceNode(InterfaceNodeId::of('Countable'), false, null), $at),
            ],
        ];

        yield 'a class that implements an interface' => [
            "<?php\nnamespace App;\nclass Written implements \\Countable {}\n",
            NodeKind::Klass,
            [$class, new ImplementsEdge($class, new GraphInterfaceNode(InterfaceNodeId::of('Countable'), false, null), $at)],
        ];

        $enum = new EnumNode(EnumNodeId::of('App\Written'), true, $at, new SymbolDeclaration());

        yield 'an enum that implements an interface' => [
            "<?php\nnamespace App;\nenum Written implements \\Countable {}\n",
            NodeKind::Enum,
            [$enum, new ImplementsEdge($enum, new GraphInterfaceNode(InterfaceNodeId::of('Countable'), false, null), $at)],
        ];

        yield 'a class that uses a trait' => [
            "<?php\nnamespace App;\nclass Written { use \\App\\Shared; }\n",
            NodeKind::Klass,
            [$class, new TraitUseEdge($class, new TraitNode(TraitNodeId::of('App\Shared'), false, null), $at)],
        ];

        $trait = new TraitNode(TraitNodeId::of('App\Written'), true, $at, new SymbolDeclaration());

        yield 'a trait that uses a trait' => [
            "<?php\nnamespace App;\ntrait Written { use \\App\\Shared; }\n",
            NodeKind::Trait,
            [$trait, new TraitUseEdge($trait, new TraitNode(TraitNodeId::of('App\Shared'), false, null), $at)],
        ];

        yield 'an interface uses no trait' => ["<?php\nnamespace App;\ninterface Written {}\n", NodeKind::Interface, [$interface]];

        $marked = new ClassNode(ClassNodeId::of('App\Written'), true, $at, new SymbolDeclaration(attributes: [new AttributeUsage('App\Marker')]));

        yield 'a declaration carrying an attribute' => [
            "<?php\nnamespace App;\n#[\\App\\Marker]\nclass Written {}\n",
            NodeKind::Klass,
            [$marked, new AttributeEdge($marked, new ClassNode(ClassNodeId::of('App\Marker'), false, null), new FileMeta('/project/Written.php', 3, 1, 23))],
        ];
    }

    #[DataProvider('providerKinds')]
    public function testOwnerNodeStandsForADeclarationOfThatKind(NodeKind $kind, Node $expected): void
    {
        self::assertEquals($expected, DeclarationEmitter::ownerNode($kind, 'App\Written'));
    }

    /**
     * @return iterable<string, array{NodeKind, Node}>
     */
    public static function providerKinds(): iterable
    {
        yield 'a class' => [NodeKind::Klass, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'an interface' => [NodeKind::Interface, new GraphInterfaceNode(InterfaceNodeId::of('App\Written'), true, null)];

        yield 'a trait' => [NodeKind::Trait, new TraitNode(TraitNodeId::of('App\Written'), true, null)];

        yield 'an enum' => [NodeKind::Enum, new EnumNode(EnumNodeId::of('App\Written'), true, null)];

        yield 'a constant stands for a class' => [NodeKind::Constant, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'an enum case stands for a class' => [NodeKind::EnumCase, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'a function stands for a class' => [NodeKind::Function, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'a method stands for a class' => [NodeKind::Method, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'a property stands for a class' => [NodeKind::Property, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'a builtin stands for a class' => [NodeKind::Builtin, new ClassNode(ClassNodeId::of('App\Written'), true, null)];

        yield 'anything else stands for a class' => [NodeKind::Unknown, new ClassNode(ClassNodeId::of('App\Written'), true, null)];
    }

    public function testOwnerNodeOfASymbolBeingDeclaredStandsWhereItIsWritten(): void
    {
        $meta = new FileMeta('/project/Written.php', 3, 1);
        $declaration = new SymbolDeclaration(attributes: [new AttributeUsage('App\Marker')]);

        self::assertEquals(
            new ClassNode(ClassNodeId::of('App\Written'), true, $meta, $declaration),
            DeclarationEmitter::ownerNode(NodeKind::Klass, 'App\Written', $meta, $declaration),
        );
    }

    public function testAttributesRecordsNoneWhereNoneAreWritten(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Written.php');

        self::assertSame([], DeclarationEmitter::attributes([], new ClassNode(ClassNodeId::of('App\Written'), true, null), $scope));
    }

    public function testAttributesRecordsEachAttributeWhereItIsWritten(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\n#[\\App\\First]\n#[\\App\\Second]\nclass Written {}\n") ?? []);
        $declaration = (new NodeFinder())->findFirstInstanceOf($parsed, ClassLike::class);
        self::assertNotNull($declaration);
        $written = new ClassNode(ClassNodeId::of('App\Written'), true, null);

        self::assertEquals(
            [
                new AttributeEdge($written, new ClassNode(ClassNodeId::of('App\First'), false, null), new FileMeta('/project/Written.php', 3, 1, 23)),
                new AttributeEdge($written, new ClassNode(ClassNodeId::of('App\Second'), false, null), new FileMeta('/project/Written.php', 4, 1, 37)),
            ],
            DeclarationEmitter::attributes($declaration->attrGroups, $written, AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Written.php')),
        );
    }

    public function testInheritanceRecordsWhatADeclarationTakesOn(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Written extends \\App\\Record {}\n") ?? []);
        $declaration = (new NodeFinder())->findFirstInstanceOf($parsed, ClassLike::class);
        self::assertNotNull($declaration);
        $written = new ClassNode(ClassNodeId::of('App\Written'), true, null);
        $meta = new FileMeta('/project/Written.php', 3, 1);

        self::assertEquals(
            [new ExtendsEdge($written, new ClassNode(ClassNodeId::of('App\Record'), false, null), $meta)],
            DeclarationEmitter::inheritance($declaration, $written, $meta),
        );
    }
}
