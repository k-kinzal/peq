<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\Emitter\DeclarationEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GraphSpelling;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(DeclarationEmitter::class)]
#[Medium]
final class DeclarationEmitterTest extends TestCase
{
    /**
     * @param list<string> $expected What the declaration is expected to record
     */
    #[DataProvider('providerDeclarations')]
    public function testEmitRecordsWhatADeclarationIsBuiltFrom(string $code, array $expected): void
    {
        $declaration = ParsedSnippet::classLike($code);
        $kind = ClassLikeDeclaration::kindOf($declaration);
        self::assertNotNull($kind);
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Written.php' => $code]), '/project/Written.php');

        self::assertSame($expected, GraphSpelling::of(DeclarationEmitter::emit($declaration, $kind, 'App\Written', $scope)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeclarations(): iterable
    {
        yield 'a class' => ["<?php\nnamespace App;\nclass Written {}\n", ['class App\Written']];

        yield 'a class that extends another' => [
            "<?php\nnamespace App;\nclass Written extends \\App\\Record {}\n",
            ['class App\Written', 'App\Written -[declaration-extends]-> App\Record'],
        ];

        yield 'an interface that extends two others' => [
            "<?php\nnamespace App;\ninterface Written extends \\App\\Readable, \\Countable {}\n",
            ['interface App\Written', 'App\Written -[declaration-extends]-> App\Readable', 'App\Written -[declaration-extends]-> Countable'],
        ];

        yield 'a class that implements an interface' => [
            "<?php\nnamespace App;\nclass Written implements \\Countable {}\n",
            ['class App\Written', 'App\Written -[declaration-implements]-> Countable'],
        ];

        yield 'an enum that implements an interface' => [
            "<?php\nnamespace App;\nenum Written implements \\Countable {}\n",
            ['enum App\Written', 'App\Written -[declaration-implements]-> Countable'],
        ];

        yield 'a class that uses a trait' => [
            "<?php\nnamespace App;\nclass Written { use \\App\\Shared; }\n",
            ['class App\Written', 'App\Written -[declaration-trait-use]-> App\Shared'],
        ];

        yield 'a trait that uses a trait' => [
            "<?php\nnamespace App;\ntrait Written { use \\App\\Shared; }\n",
            ['trait App\Written', 'App\Written -[declaration-trait-use]-> App\Shared'],
        ];

        yield 'an interface uses no trait' => ["<?php\nnamespace App;\ninterface Written {}\n", ['interface App\Written']];

        yield 'a declaration carrying an attribute' => [
            "<?php\nnamespace App;\n#[\\App\\Marker]\nclass Written {}\n",
            ['class App\Written', 'App\Written -[attribute]-> App\Marker'],
        ];
    }

    #[DataProvider('providerKinds')]
    public function testOwnerNodeStandsForADeclarationOfThatKind(NodeKind $kind, string $expected): void
    {
        self::assertSame($expected, DeclarationEmitter::ownerNode($kind, 'App\Written')->kind()->value);
    }

    /**
     * @return iterable<string, array{NodeKind, string}>
     */
    public static function providerKinds(): iterable
    {
        yield 'a class' => [NodeKind::Klass, 'class'];

        yield 'an interface' => [NodeKind::Interface, 'interface'];

        yield 'a trait' => [NodeKind::Trait, 'trait'];

        yield 'an enum' => [NodeKind::Enum, 'enum'];

        yield 'anything else stands for a class' => [NodeKind::Unknown, 'class'];
    }

    public function testOwnerNodeOfASymbolOnlyBeingReferredToStandsNowhere(): void
    {
        self::assertNull(DeclarationEmitter::ownerNode(NodeKind::Klass, 'App\Written')->meta());
    }

    public function testOwnerNodeOfASymbolBeingDeclaredStandsWhereItIsWritten(): void
    {
        $meta = new FileMeta('/project/Written.php', 3, 1);

        self::assertSame($meta, DeclarationEmitter::ownerNode(NodeKind::Klass, 'App\Written', $meta)->meta());
    }

    public function testAttributesRecordsNoneWhereNoneAreWritten(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Written.php' => "<?php\nnamespace App;\nclass Written {}\n"]), '/project/Written.php');

        self::assertSame([], DeclarationEmitter::attributes([], DeclarationEmitter::ownerNode(NodeKind::Klass, 'App\Written'), $scope));
    }

    public function testInheritanceRecordsWhatADeclarationTakesOn(): void
    {
        $declaration = ParsedSnippet::classLike("<?php\nnamespace App;\nclass Written extends \\App\\Record {}\n");
        $recorded = DeclarationEmitter::inheritance($declaration, DeclarationEmitter::ownerNode(NodeKind::Klass, 'App\Written'), new FileMeta('/project/Written.php', 3, 1));

        self::assertSame('App\Record', $recorded[0]->to()->toString());
    }
}
