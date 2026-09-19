<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MatchClause::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class MatchClauseTest extends TestCase
{
    #[DataProvider('providerOnePattern')]
    public function testAMatchCarriesTheShapeItLooksFor(GraphPattern $pattern): void
    {
        self::assertSame($pattern, (new MatchClause($pattern))->pattern);
    }

    #[DataProvider('providerOnePattern')]
    public function testAMatchThatNarrowsNothingAfterwardsSaysSo(GraphPattern $pattern): void
    {
        self::assertNull((new MatchClause($pattern))->where);
    }

    #[DataProvider('providerOnePattern')]
    public function testAMatchCarriesWhatNarrowsItsMatches(GraphPattern $pattern): void
    {
        $where = new VariableExpression('p');

        self::assertSame($where, (new MatchClause($pattern, $where))->where);
    }

    #[DataProvider('providerOnePattern')]
    public function testAMatchIsNotOptionalUnlessItSaysSo(GraphPattern $pattern): void
    {
        self::assertFalse((new MatchClause($pattern))->optional);
    }

    #[DataProvider('providerOnePattern')]
    public function testAnOptionalMatchSaysThatItKeepsRowsItDidNotMatch(GraphPattern $pattern): void
    {
        self::assertTrue((new MatchClause($pattern, null, true))->optional);
    }

    /**
     * @return iterable<string, array{GraphPattern}>
     */
    public static function providerOnePattern(): iterable
    {
        yield 'a pattern of one symbol' => [new GraphPattern([new PathPattern([new NodePattern('p')])])];
    }
}
