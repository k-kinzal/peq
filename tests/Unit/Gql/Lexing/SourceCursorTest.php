<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Lexing;

use App\Gql\Lexing\SourceCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceCursor::class)]
#[Small]
final class SourceCursorTest extends TestCase
{
    public function testAtEndReportsACursorOverNothingAsFinished(): void
    {
        self::assertTrue((new SourceCursor(''))->atEnd());
    }

    public function testAtEndReportsACursorWithSomethingLeftAsUnfinished(): void
    {
        self::assertFalse((new SourceCursor('MATCH'))->atEnd());
    }

    public function testPeekReadsTheCharacterTheCursorStandsOn(): void
    {
        self::assertSame('M', (new SourceCursor('MATCH'))->peek());
    }

    public function testPeekReadsNothingPastTheEnd(): void
    {
        self::assertSame('', (new SourceCursor('M'))->peek(1));
    }

    public function testMatchesReportsWhatTheCursorIsAboutToRead(): void
    {
        self::assertTrue((new SourceCursor('<= 3'))->matches('<='));
    }

    public function testMatchesReportsWhatItIsNot(): void
    {
        self::assertFalse((new SourceCursor('<= 3'))->matches('>='));
    }

    public function testCaptureReadsOnlyWhatStandsAtTheCursor(): void
    {
        self::assertSame('total42', (new SourceCursor('total42 '))->capture('/[A-Za-z_][A-Za-z0-9_]{0,63}/A'));
    }

    public function testCaptureReadsNothingWhenThePatternDoesNotMatchThere(): void
    {
        self::assertNull((new SourceCursor('42'))->capture('/[A-Za-z_][A-Za-z0-9_]{0,63}/A'));
    }

    public function testTakeReturnsWhatItConsumesAndMovesPastIt(): void
    {
        $cursor = new SourceCursor('MATCH (p)');

        self::assertSame('MATCH', $cursor->take(5));
        self::assertSame(' ', $cursor->peek());
    }

    #[DataProvider('providerTextWithLineBreaks')]
    public function testTakeCountsTheLinesItPassesOver(string $text, int $length, int $line, int $column): void
    {
        $cursor = new SourceCursor($text);
        $cursor->take($length);

        self::assertSame($line, $cursor->line());
        self::assertSame($column, $cursor->column());
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function providerTextWithLineBreaks(): iterable
    {
        yield 'nothing taken stays where it was' => ['a', 0, 1, 1];

        yield 'text with no break stays on its line' => ['abc', 3, 1, 4];

        yield 'a break moves to the next line' => ["a\nb", 2, 2, 1];

        yield 'a break with text after it counts the text' => ["a\nbc", 4, 2, 3];

        yield 'two breaks move two lines' => ["a\nb\nc", 4, 3, 1];
    }

    public function testLineStartsAtTheFirstLine(): void
    {
        self::assertSame(1, (new SourceCursor('MATCH'))->line());
    }

    public function testColumnStartsAtTheFirstColumn(): void
    {
        self::assertSame(1, (new SourceCursor('MATCH'))->column());
    }

    public function testOffsetStartsAtTheBeginningOfTheText(): void
    {
        self::assertSame(0, (new SourceCursor('MATCH'))->offset());
    }

    public function testOffsetCountsWhatHasBeenConsumed(): void
    {
        $cursor = new SourceCursor('MATCH (p)');
        $cursor->take(6);

        self::assertSame(6, $cursor->offset());
    }

    public function testCharactersCountsACharacterWrittenInSeveralBytesOnce(): void
    {
        self::assertSame(2, SourceCursor::characters('顧客'));
    }

    public function testCharactersCountsACharacterCutInTheMiddleOnce(): void
    {
        self::assertSame(1, SourceCursor::characters(substr('顧', 0, 2)));
    }

    public function testTakeCountsColumnsInCharactersRatherThanBytes(): void
    {
        $cursor = new SourceCursor('顧客 x');
        $cursor->take(strlen('顧客 '));

        self::assertSame(4, $cursor->column());
    }
}
