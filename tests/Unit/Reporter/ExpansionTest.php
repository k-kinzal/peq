<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Reporter\Continuation;
use App\Reporter\Expansion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Expansion::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(BuiltinNode::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class ExpansionTest extends TestCase
{
    public function testReachOpensOutASymbolItHasNotMetBefore(): void
    {
        $expansion = new Expansion();

        self::assertSame(Continuation::Descends, $expansion->reach(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    public function testReachCallsASymbolAlreadyOpenOnThePathACycle(): void
    {
        $expansion = new Expansion();
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $expansion->reach($root, 0);
        $expansion->reach(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertSame(Continuation::Cycle, $expansion->reach($root, 2));
    }

    public function testReachCallsASymbolExpandedOnAnotherBranchARepeat(): void
    {
        $expansion = new Expansion();
        $shared = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $expansion->reach(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $expansion->reach($shared, 1);
        $expansion->reach(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertSame(Continuation::Repeat, $expansion->reach($shared, 2));
    }

    public function testReachPrefersTheCycleWhenASymbolIsBothOnThePathAndExpandedAlready(): void
    {
        $expansion = new Expansion();
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $expansion->reach($root, 0);

        self::assertSame(Continuation::Cycle, $expansion->reach($root, 1));
    }

    public function testReachCallsASymbolOutsideTheAnalyzedSourcesALeaf(): void
    {
        $expansion = new Expansion();

        self::assertSame(Continuation::Leaf, $expansion->reach(new BuiltinNode(BuiltinNodeId::of('int'), true), 0));
    }

    public function testReachPutsWhatLiesPastTheLevelBoundOutOfTheReport(): void
    {
        $expansion = new Expansion(1);

        self::assertSame(Continuation::Beyond, $expansion->reach(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2));
    }

    public function testReachKeepsWhatSitsExactlyAtTheLevelBound(): void
    {
        $expansion = new Expansion(1);

        self::assertSame(Continuation::Descends, $expansion->reach(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1));
    }

    public function testReachBoundsNothingWhenNoLevelIsGiven(): void
    {
        $expansion = new Expansion();

        self::assertSame(Continuation::Descends, $expansion->reach(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 9));
    }

    public function testReachDoesNotRememberASymbolItRefusedToOpenOut(): void
    {
        $expansion = new Expansion(1);
        $node = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $expansion->reach($node, 2);

        self::assertSame(Continuation::Descends, $expansion->reach($node, 0));
    }

    public function testReachForgetsAPathItHasWalkedBackOutOf(): void
    {
        $expansion = new Expansion();
        $shared = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $expansion->reach(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $expansion->reach(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $expansion->reach($shared, 2);

        self::assertSame(Continuation::Repeat, $expansion->reach($shared, 1));
    }

    public function testReachOpensOutASymbolOnlyOnceHoweverManyBranchesReachIt(): void
    {
        $expansion = new Expansion();
        $shared = new ClassNode(ClassNodeId::of('App\Domain\Money'), true);

        $expansion->reach(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $expansion->reach($shared, 1);
        $expansion->reach($shared, 1);

        self::assertSame(Continuation::Repeat, $expansion->reach($shared, 1));
    }

    #[DataProvider('providerKindsWithNothingBelowThem')]
    public function testIsLeafKindRecognisesASymbolOutsideTheAnalyzedSources(NodeKind $kind): void
    {
        self::assertTrue(Expansion::isLeafKind($kind));
    }

    /**
     * @return iterable<string, array{NodeKind}>
     */
    public static function providerKindsWithNothingBelowThem(): iterable
    {
        yield 'a builtin type' => [NodeKind::Builtin];

        yield 'an unresolved symbol' => [NodeKind::Unknown];
    }

    #[DataProvider('providerKindsThatCanRelateToOthers')]
    public function testIsLeafKindRecognisesASymbolThatCanRelateToOthers(NodeKind $kind): void
    {
        self::assertFalse(Expansion::isLeafKind($kind));
    }

    /**
     * @return iterable<string, array{NodeKind}>
     */
    public static function providerKindsThatCanRelateToOthers(): iterable
    {
        foreach (NodeKind::cases() as $kind) {
            if ($kind !== NodeKind::Builtin && $kind !== NodeKind::Unknown) {
                yield $kind->value => [$kind];
            }
        }
    }
}
