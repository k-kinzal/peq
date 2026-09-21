<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgePattern::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(Quantifier::class)]
#[Small]
final class EdgePatternTest extends TestCase
{
    public function testAnEdgePatternCarriesWhichWayItCrossesTheRelation(): void
    {
        self::assertSame(EdgeDirection::Against, (new EdgePattern(EdgeDirection::Against))->direction);
    }

    public function testAnEdgePatternCarriesWhatItBindsAndWhatItRequires(): void
    {
        $labels = LabelPattern::named('methodCall');
        $filter = new ElementFilter();
        $pattern = new EdgePattern(EdgeDirection::Along, 'e', $labels, $filter);

        self::assertSame('e', $pattern->variable);
        self::assertSame($labels, $pattern->labels);
        self::assertSame($filter, $pattern->filter);
    }

    public function testAnEdgePatternIsCrossedOnceUnlessARepetitionSaysOtherwise(): void
    {
        self::assertNull((new EdgePattern(EdgeDirection::Along))->quantifier);
    }

    public function testAnEdgePatternCarriesTheRepetitionItWasGiven(): void
    {
        $quantifier = new Quantifier(1, 3);
        $pattern = new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), $quantifier);

        self::assertSame($quantifier, $pattern->quantifier);
    }
}
