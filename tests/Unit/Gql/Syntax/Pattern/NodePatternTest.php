<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NodePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(LabelOperator::class)]
#[Small]
final class NodePatternTest extends TestCase
{
    public function testANodePatternCarriesWhatItBindsAndWhatItRequires(): void
    {
        $labels = LabelPattern::named('Method');
        $filter = new ElementFilter();
        $pattern = new NodePattern('p', $labels, $filter);

        self::assertSame('p', $pattern->variable);
        self::assertSame($labels, $pattern->labels);
        self::assertSame($filter, $pattern->filter);
    }

    public function testANodePatternThatRequiresNothingAndBindsNothingIsStillOne(): void
    {
        $pattern = new NodePattern();

        self::assertNull($pattern->variable);
        self::assertNull($pattern->labels);
        self::assertTrue($pattern->filter->empty());
    }
}
