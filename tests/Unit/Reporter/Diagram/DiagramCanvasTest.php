<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\DiagramCanvas;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DiagramCanvas::class)]
#[Small]
final class DiagramCanvasTest extends TestCase
{
    public function testArrowGivesEveryArrowAMarkOfItsOwn(): void
    {
        $canvas = new DiagramCanvas();

        self::assertSame([1, 2, 3], [$canvas->arrow(), $canvas->arrow(), $canvas->arrow()]);
    }

    public function testWriteWritesTextWhereItIsPut(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 2, 'ab');

        self::assertSame(['  ab'], $canvas->lines());
    }

    public function testWriteGivesAWideCharacterTwoColumns(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, '請求');
        $canvas->write(0, 4, 'x');

        self::assertSame(['請', '', '求', '', 'x'], $canvas->cells(0));
    }

    public function testWriteWritesOverWhatWasWrittenBeforeInTheSameColumns(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'abc');
        $canvas->write(0, 1, 'X');

        self::assertSame(['aXc'], $canvas->lines());
    }

    public function testWriteWritesEachLineApart(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(2, 0, 'b');
        $canvas->write(0, 0, 'a');

        self::assertSame(['a', 'b'], $canvas->lines());
    }

    public function testAcrossDrawsALineWithDashes(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->across(0, 1, 3, $canvas->arrow());

        self::assertSame([' ───'], $canvas->lines());
    }

    public function testDownJoinsALineAcrossWhereTheyMeet(): void
    {
        $canvas = new DiagramCanvas();
        $arrow = $canvas->arrow();
        $canvas->write(0, 0, 'a');
        $canvas->down(1, 0, 1, $arrow);
        $canvas->across(1, 1, 3, $arrow);

        self::assertSame(['a│', ' └──'], $canvas->lines());
    }

    public function testDownTurnsIntoALineAcrossAtBothEnds(): void
    {
        $canvas = new DiagramCanvas();
        $arrow = $canvas->arrow();
        $canvas->across(0, 0, 2, $arrow);
        $canvas->down(2, 0, 2, $arrow);
        $canvas->across(2, 2, 4, $arrow);

        self::assertSame(['──┐', '  └──'], $canvas->lines());
    }

    public function testDownCrossesALineAcrossOfAnotherArrowWithoutJoiningIt(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'a');
        $canvas->down(1, 0, 2, $canvas->arrow());
        $canvas->across(1, 0, 2, $canvas->arrow());

        self::assertSame(['a│', '─│─'], $canvas->lines());
    }

    public function testDownDrawsAVerticalLineOnEveryLineItPasses(): void
    {
        $canvas = new DiagramCanvas();
        $arrow = $canvas->arrow();
        $canvas->write(1, 0, 'a');
        $canvas->down(1, 0, 2, $arrow);

        self::assertSame(['a│'], $canvas->lines());
    }

    public function testJoinJoinsACellOnTheSidesItIsGiven(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->join(0, 0, $canvas->arrow(), DiagramCanvas::NORTH | DiagramCanvas::EAST);

        self::assertSame(['└'], $canvas->lines());
    }

    public function testJoinAddsToTheSidesACellIsAlreadyJoinedOnForTheSameArrow(): void
    {
        $canvas = new DiagramCanvas();
        $arrow = $canvas->arrow();
        $canvas->join(0, 0, $arrow, DiagramCanvas::SOUTH);
        $canvas->join(0, 0, $arrow, DiagramCanvas::EAST);

        self::assertSame(['┌'], $canvas->lines());
    }

    public function testJoinOfTwoArrowsMeetingAtTheEndOfOneIsATurn(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->join(0, 0, $canvas->arrow(), DiagramCanvas::NORTH);
        $canvas->join(0, 0, $canvas->arrow(), DiagramCanvas::EAST);

        self::assertSame(['└'], $canvas->lines());
    }

    public function testLinesIsNothingWhenNothingWasDrawn(): void
    {
        self::assertSame([], (new DiagramCanvas())->lines());
    }

    public function testLinesLeavesOutALineHoldingNothingButAVerticalLine(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'a');
        $canvas->down(1, 0, 2, $canvas->arrow());
        $canvas->write(2, 0, 'b');

        self::assertSame(['a│', 'b│'], $canvas->lines());
    }

    public function testLinesLeavesOutALineHoldingNothingButVerticalLinesOfSeveralArrows(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'a');
        $canvas->down(1, 0, 2, $canvas->arrow());
        $canvas->down(3, 0, 2, $canvas->arrow());
        $canvas->write(2, 0, 'b');

        self::assertSame(['a│ │', 'b│ │'], $canvas->lines());
    }

    public function testLinesKeepsALineThatHoldsOnlyALineAcross(): void
    {
        $canvas = new DiagramCanvas();
        $arrow = $canvas->arrow();
        $canvas->down(0, 0, 1, $arrow);
        $canvas->across(1, 0, 2, $arrow);

        self::assertSame(['└──'], $canvas->lines());
    }

    public function testLinesKeepsEveryLineTextWasWrittenOn(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, ' ');
        $canvas->write(1, 0, 'a');

        self::assertSame(['', 'a'], $canvas->lines());
    }

    public function testLinesPutsTheLinesInOrderFromTheTopWhereverTheyStart(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(1, 0, 'b');
        $canvas->write(-1, 0, 'a');

        self::assertSame(['a', 'b'], $canvas->lines());
    }

    public function testLinesDropsTheSpacesAtTheEndOfALine(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'a  ');

        self::assertSame(['a'], $canvas->lines());
    }

    public function testLinesLinesUpWhatFollowsAWideCharacterByTheColumnsItTakes(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, '請求書');
        $canvas->across(0, 7, 8, $canvas->arrow());

        self::assertSame(['請求書 ──'], $canvas->lines());
    }

    public function testCellsLetsTextWinOverALineDrawnBeneathIt(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->across(0, 0, 2, $canvas->arrow());
        $canvas->write(0, 2, '▶');

        self::assertSame(['─', '─', '▶'], $canvas->cells(0));
    }

    public function testCellsLeavesAnEmptyStringWhereAWideCharacterRunsOn(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 1, '請');

        self::assertSame([' ', '請', ''], $canvas->cells(0));
    }

    public function testCellsIsNothingForALineNothingWasDrawnOn(): void
    {
        $canvas = new DiagramCanvas();
        $canvas->write(0, 0, 'a');

        self::assertSame([], $canvas->cells(1));
    }

    public function testGlyphJoinsTheStrokesOfOneArrow(): void
    {
        self::assertSame(
            '├',
            DiagramCanvas::glyph([1 => DiagramCanvas::NORTH | DiagramCanvas::SOUTH | DiagramCanvas::EAST]),
        );
    }

    public function testGlyphJoinsTheStrokesOfTwoArrowsThatMeetWhereOneEnds(): void
    {
        self::assertSame(
            '┴',
            DiagramCanvas::glyph([
                1 => DiagramCanvas::NORTH,
                2 => DiagramCanvas::EAST | DiagramCanvas::WEST,
            ]),
        );
    }

    public function testGlyphDrawsTwoArrowsPassingStraightThroughOneAnotherAsAnUnbrokenVerticalLine(): void
    {
        self::assertSame(
            '│',
            DiagramCanvas::glyph([
                1 => DiagramCanvas::NORTH | DiagramCanvas::SOUTH,
                2 => DiagramCanvas::EAST | DiagramCanvas::WEST,
            ]),
        );
    }

    public function testGlyphDrawsOneArrowJoinedOnEverySideAsAJoinOfFour(): void
    {
        self::assertSame(
            '┼',
            DiagramCanvas::glyph([
                1 => DiagramCanvas::NORTH | DiagramCanvas::SOUTH | DiagramCanvas::EAST | DiagramCanvas::WEST,
            ]),
        );
    }

    public function testGlyphOfACellNoArrowPassesThroughIsASpace(): void
    {
        self::assertSame(' ', DiagramCanvas::glyph([]));
    }

    #[DataProvider('providerEveryWayACellCanBeJoined')]
    public function testGlyphDrawsEveryWayACellCanBeJoined(int $sides, string $expected): void
    {
        self::assertSame($expected, DiagramCanvas::glyph([1 => $sides]));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function providerEveryWayACellCanBeJoined(): iterable
    {
        yield 'nowhere' => [0, ' '];

        yield 'above' => [DiagramCanvas::NORTH, '│'];

        yield 'below' => [DiagramCanvas::SOUTH, '│'];

        yield 'above and below' => [DiagramCanvas::NORTH | DiagramCanvas::SOUTH, '│'];

        yield 'on the right' => [DiagramCanvas::EAST, '─'];

        yield 'on the left' => [DiagramCanvas::WEST, '─'];

        yield 'on both sides' => [DiagramCanvas::EAST | DiagramCanvas::WEST, '─'];

        yield 'below and on the right' => [DiagramCanvas::SOUTH | DiagramCanvas::EAST, '┌'];

        yield 'below and on the left' => [DiagramCanvas::SOUTH | DiagramCanvas::WEST, '┐'];

        yield 'above and on the right' => [DiagramCanvas::NORTH | DiagramCanvas::EAST, '└'];

        yield 'above and on the left' => [DiagramCanvas::NORTH | DiagramCanvas::WEST, '┘'];

        yield 'above, below and on the right' => [DiagramCanvas::NORTH | DiagramCanvas::SOUTH | DiagramCanvas::EAST, '├'];

        yield 'above, below and on the left' => [DiagramCanvas::NORTH | DiagramCanvas::SOUTH | DiagramCanvas::WEST, '┤'];

        yield 'on both sides and below' => [DiagramCanvas::EAST | DiagramCanvas::WEST | DiagramCanvas::SOUTH, '┬'];

        yield 'on both sides and above' => [DiagramCanvas::EAST | DiagramCanvas::WEST | DiagramCanvas::NORTH, '┴'];

        yield 'on every side' => [DiagramCanvas::NORTH | DiagramCanvas::EAST | DiagramCanvas::SOUTH | DiagramCanvas::WEST, '┼'];
    }
}
