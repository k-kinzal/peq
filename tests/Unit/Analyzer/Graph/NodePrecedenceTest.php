<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodePrecedence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NodePrecedence::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class NodePrecedenceTest extends TestCase
{
    public function testDescribesWeighsAPlaceholderAtNothing(): void
    {
        $placeholder = UnknownNode::standingInFor(ClassNodeId::of('App\Domain\Invoice'));

        self::assertSame(0, NodePrecedence::describes($placeholder));
    }

    public function testDescribesWeighsADeclarationReadInFullAtEverything(): void
    {
        $read = new ClassNode(
            ClassNodeId::of('App\Domain\Invoice'),
            true,
            new FileMeta('/project/src/Invoice.php', 12, 1),
            new SymbolDeclaration(modifiers: new Modifiers(final: true)),
        );

        self::assertSame(15, NodePrecedence::describes($read));
    }

    #[DataProvider('providerDescriptionsFromLeastToMostInformative')]
    public function testDescribesRanksADescriptionAboveTheOneItSaysMoreThan(Node $lesser, Node $greater): void
    {
        self::assertLessThan(NodePrecedence::describes($greater), NodePrecedence::describes($lesser));
    }

    #[DataProvider('providerDescriptionsFromLeastToMostInformative')]
    public function testPrefersTakesTheDescriptionThatSaysMore(Node $lesser, Node $greater): void
    {
        self::assertTrue(NodePrecedence::prefers($lesser, $greater));
    }

    #[DataProvider('providerDescriptionsFromLeastToMostInformative')]
    public function testPrefersKeepsTheDescriptionThatSaysMore(Node $lesser, Node $greater): void
    {
        self::assertFalse(NodePrecedence::prefers($greater, $lesser));
    }

    /**
     * @return iterable<string, array{Node, Node}>
     */
    public static function providerDescriptionsFromLeastToMostInformative(): iterable
    {
        $named = ClassNodeId::of('App\Domain\Invoice');
        $written = new FileMeta('/project/src/Invoice.php', 12, 1);
        $says = new SymbolDeclaration(modifiers: new Modifiers(final: true));

        yield 'a placeholder against a symbol of a known kind' => [
            UnknownNode::standingInFor($named),
            new ClassNode($named),
        ];

        yield 'a bare reference against a resolved symbol' => [
            new ClassNode($named),
            new ClassNode($named, true),
        ];

        yield 'a resolved symbol against one whose declaration was read' => [
            new ClassNode($named, true),
            new ClassNode($named, true, null, $says),
        ];

        yield 'a declaration without a location against one with it' => [
            new ClassNode($named, true, null, $says),
            new ClassNode($named, true, $written, $says),
        ];

        yield 'a declaration that says nothing against one that says something' => [
            new ClassNode($named, true, null, new SymbolDeclaration()),
            new ClassNode($named, true, null, $says),
        ];
    }

    public function testPrefersKeepsWhatIsRecordedWhenTwoDescriptionsSayTheSameAmount(): void
    {
        $named = ClassNodeId::of('App\Domain\Invoice');

        self::assertFalse(NodePrecedence::prefers(new ClassNode($named, true), new ClassNode($named, true)));
    }
}
