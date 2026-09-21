<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
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
#[CoversClass(GraphPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(ElementFilter::class)]
#[Small]
final class GraphPatternTest extends TestCase
{
    public function testAPatternCarriesEveryPathItMatchesTogether(): void
    {
        $first = new PathPattern([new NodePattern('a')]);
        $second = new PathPattern([new NodePattern('b')]);

        self::assertSame([$first, $second], (new GraphPattern([$first, $second]))->paths);
    }
}
