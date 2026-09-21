<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\Flow\Exits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\State::class)]
final class ExitsTest extends TestCase
{
    public function testAbsorbKeepsOnlyExitsForAnOuterLoop(): void
    {
        $state = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        $inner = new \App\Analyzer\ExperimentAnalyzer\Flow\Exits(null);
        $inner->breaks = [1 => [$state], 2 => [$state]];
        $inner->continues = [1 => [$state], 3 => [$state]];
        $outer = new \App\Analyzer\ExperimentAnalyzer\Flow\Exits(null);
        $outer->absorb($inner, true);
        self::assertSame([1 => [$state]], $outer->breaks);
        self::assertSame([2 => [$state]], $outer->continues);
        self::assertNull($outer->normal);
    }

    public function testCombineConsumesExactlyOneNestedLevel(): void
    {
        $state = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        self::assertSame([1 => [$state]], \App\Analyzer\ExperimentAnalyzer\Flow\Exits::combine([], [2 => [$state]], true));
    }
}
