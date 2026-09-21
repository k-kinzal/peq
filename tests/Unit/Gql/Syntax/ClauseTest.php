<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax;

use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FilterClause::class)]
#[CoversClass(LetClause::class)]
#[CoversClass(MatchClause::class)]
#[CoversClass(OrderByClause::class)]
#[CoversClass(PageClause::class)]
#[CoversClass(ReturnClause::class)]
#[UsesClass(SortKey::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathPattern::class)]
#[Small]
final class ClauseTest extends TestCase
{
    #[DataProvider('providerEveryKindOfClause')]
    public function testEveryStepOfAQueryIsAClauseOfItsOwnKind(Clause $clause, string $expected): void
    {
        self::assertSame($expected, $clause::class);
    }

    /**
     * @return iterable<string, array{Clause, class-string}>
     */
    public static function providerEveryKindOfClause(): iterable
    {
        $pattern = new GraphPattern([new PathPattern([new NodePattern('p')])]);
        $value = new VariableExpression('p');

        yield 'looking for a shape' => [new MatchClause($pattern), MatchClause::class];

        yield 'naming a computed value' => [new LetClause([new VariableBinding('n', $value)]), LetClause::class];

        yield 'keeping the rows a predicate holds of' => [new FilterClause($value), FilterClause::class];

        yield 'putting the rows in an order' => [new OrderByClause([new SortKey($value)]), OrderByClause::class];

        yield 'taking a stretch of the rows' => [new PageClause(0, 10), PageClause::class];

        yield 'deciding what the reader is shown' => [new ReturnClause(), ReturnClause::class];
    }
}
