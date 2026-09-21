<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\DiagramEdge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DiagramEdge::class)]
#[Small]
final class DiagramEdgeTest extends TestCase
{
    public function testAnEdgeRemembersWhichSymbolsItJoins(): void
    {
        self::assertSame('a', (new DiagramEdge('a', 'b', 'calls'))->origin);
    }

    public function testSignatureTellsARelationApartByItsTwoEndsAndWhatItIs(): void
    {
        self::assertSame('a|calls|b', (new DiagramEdge('a', 'b', 'calls'))->signature());
    }

    public function testSignatureTellsTwoRelationsBetweenTheSameSymbolsApart(): void
    {
        self::assertNotSame(
            (new DiagramEdge('a', 'b', 'calls'))->signature(),
            (new DiagramEdge('a', 'b', 'parameterType'))->signature(),
        );
    }

    public function testSignatureTellsARelationApartFromTheSameOneReadBackwards(): void
    {
        self::assertNotSame(
            (new DiagramEdge('a', 'b', 'calls'))->signature(),
            (new DiagramEdge('b', 'a', 'calls'))->signature(),
        );
    }
}
