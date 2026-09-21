<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GroupPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(ElementFilter::class)]
#[Small]
final class GroupPatternTest extends TestCase
{
    public function testAGroupCarriesTheStretchOfPatternInItsParentheses(): void
    {
        $term = new NodePattern('a');

        self::assertSame([$term], (new GroupPattern([$term]))->terms);
    }

    public function testAGroupIsMatchedOnceUnlessARepetitionSaysOtherwise(): void
    {
        self::assertNull((new GroupPattern([new NodePattern('a')]))->quantifier);
    }

    public function testAGroupCarriesTheRepetitionItWasGiven(): void
    {
        $quantifier = new Quantifier(1, 3);

        self::assertSame($quantifier, (new GroupPattern([new NodePattern('a')], $quantifier))->quantifier);
    }
}
