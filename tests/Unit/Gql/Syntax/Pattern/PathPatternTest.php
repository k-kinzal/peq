<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PathPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(ElementFilter::class)]
#[Small]
final class PathPatternTest extends TestCase
{
    public function testAPathCarriesItsPiecesInTheOrderTheyAreWritten(): void
    {
        $term = new NodePattern('a');

        self::assertSame([$term], (new PathPattern([$term]))->terms);
    }

    public function testAPathThatNamesNoModeIsAWalk(): void
    {
        self::assertSame(PathMode::Walk, (new PathPattern([new NodePattern('a')]))->mode);
    }

    public function testAPathCarriesTheModeAndTheNameItWasGiven(): void
    {
        $pattern = new PathPattern([new NodePattern('a')], PathMode::Acyclic, 'p');

        self::assertSame(PathMode::Acyclic, $pattern->mode);
        self::assertSame('p', $pattern->variable);
    }

    public function testAPathThatIsNotNamedCarriesNoName(): void
    {
        self::assertNull((new PathPattern([new NodePattern('a')]))->variable);
    }
}
