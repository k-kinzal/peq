<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\TableReporter;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Reporter\TableReporter\TableCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TableCursor::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(BuiltinNodeId::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(BuiltinNode::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Reporter\Continuation::class)]
#[UsesClass(\App\Reporter\Expansion::class)]
#[Small]
final class TableCursorTest extends TestCase
{
    public function testVisitWritesADepthASymbolAKindAndAPlaceForTheSymbolItIsGiven(): void
    {
        $cursor = new TableCursor();

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, new FileMeta('/project/src/Domain/Invoice.php', 12, 1)), 0);

        self::assertSame(
            [['0', 'App\Domain\Invoice', 'class', '/project/src/Domain/Invoice.php:12']],
            $cursor->rows(),
        );
    }

    public function testVisitAsksTheWalkToContinueBelowAnExpandableSymbol(): void
    {
        self::assertTrue((new TableCursor())->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0));
    }

    public function testVisitWritesNoRowBelowTheBoundedLevel(): void
    {
        $cursor = new TableCursor(1);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 2);

        self::assertSame([], $cursor->rows());
    }

    public function testVisitWritesTheDepthTheSymbolSitsAt(): void
    {
        $cursor = new TableCursor();

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);

        self::assertSame(['0', '1'], array_column($cursor->rows(), 0));
    }

    public function testVisitNamesABranchItCutOnACycle(): void
    {
        $cursor = new TableCursor();
        $root = new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);

        $cursor->visit($root, 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit($root, 2);

        self::assertSame('App\Domain\Invoice (recursive)', $cursor->rows()[2][1] ?? null);
    }

    public function testVisitNamesABranchItCutOnASymbolExpandedElsewhere(): void
    {
        $cursor = new TableCursor();
        $shared = new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true);

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit($shared, 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit($shared, 2);

        self::assertSame('App\Domain\Money::add (repeated)', $cursor->rows()[3][1] ?? null);
    }

    public function testVisitLeavesThePlaceEmptyForASymbolAnalysisNeverLocated(): void
    {
        $cursor = new TableCursor();

        $cursor->visit(new BuiltinNode(BuiltinNodeId::of('int'), true), 0);

        self::assertSame([['0', 'int', 'builtin', '-']], $cursor->rows());
    }

    public function testRowsIsEmptyBeforeTheWalkHasReachedAnything(): void
    {
        self::assertSame([], (new TableCursor())->rows());
    }

    public function testRowsKeepsTheOrderTheWalkReachedTheSymbolsIn(): void
    {
        $cursor = new TableCursor();

        $cursor->visit(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true), 0);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true), 1);
        $cursor->visit(new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true), 2);

        self::assertSame(
            ['App\Domain\Invoice', 'App\Domain\Invoice::total', 'App\Domain\Money::add'],
            array_column($cursor->rows(), 1),
        );
    }
}
