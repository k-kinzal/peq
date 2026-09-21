<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Execution;

use App\Gql\Datum\IntegerDatum;
use App\Gql\Execution\QuantifierLimit;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\VariableBinding;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use App\Gql\Syntax\Query;
use App\Gql\Syntax\QueryBlock;
use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QuantifierLimit::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(LetClause::class)]
#[UsesClass(MatchClause::class)]
#[UsesClass(ReturnClause::class)]
#[UsesClass(VariableBinding::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(Query::class)]
#[UsesClass(QueryBlock::class)]
#[UsesClass(SetOperator::class)]
#[Small]
final class QuantifierLimitTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testCheckLeavesAQuantifierWrittenUpToTheLimitAlone(): void
    {
        $query = Query::of(new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern(
                    [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 3)), new NodePattern('b')],
                    PathMode::Walk,
                ),
            ])),
            new ReturnClause(),
        ]));

        $this->expectNotToPerformAssertions();

        QuantifierLimit::check($query, 3);
    }

    /**
     * @throws GqlException
     */
    public function testCheckRefusesAQuantifierWrittenBeyondTheLimitAndSaysHowToRaiseIt(): void
    {
        $query = Query::of(new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern(
                    [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 4)), new NodePattern('b')],
                    PathMode::Walk,
                ),
            ])),
            new ReturnClause(),
        ]));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage(
            '[42001] error: syntax error or access rule violation - invalid syntax: a quantifier may be written with an upper bound of at most 3 here,'
            .' and one is written with 4: raise --hops to allow more',
        );

        QuantifierLimit::check($query, 3);
    }

    /**
     * @throws GqlException
     */
    public function testCheckLeavesAQuantifierWithNoUpperBoundAlone(): void
    {
        $query = Query::of(new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern(
                    [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, null)), new NodePattern('b')],
                    PathMode::Trail,
                ),
            ])),
            new ReturnClause(),
        ]));

        $this->expectNotToPerformAssertions();

        QuantifierLimit::check($query, 0);
    }

    /**
     * @throws GqlException
     */
    public function testCheckRefusesAQuantifierBeyondTheLimitInAnyPathOfAMatch(): void
    {
        $query = Query::of(new QueryBlock([
            new MatchClause(new GraphPattern([
                new PathPattern([new NodePattern('a')], PathMode::Walk),
                new PathPattern(
                    [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 5)), new NodePattern('b')],
                    PathMode::Walk,
                ),
            ])),
            new ReturnClause(),
        ]));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quantifier may be written with an upper bound of at most 3 here, and one is written with 5');

        QuantifierLimit::check($query, 3);
    }

    /**
     * @throws GqlException
     */
    public function testCheckRefusesAQuantifierBeyondTheLimitInAnyBlockOfTheQuery(): void
    {
        $query = new Query(
            [
                new QueryBlock([new ReturnClause()]),
                new QueryBlock([
                    new MatchClause(new GraphPattern([
                        new PathPattern(
                            [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 4)), new NodePattern('b')],
                            PathMode::Walk,
                        ),
                    ])),
                    new ReturnClause(),
                ]),
            ],
            [SetOperator::UnionAll],
        );

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quantifier may be written with an upper bound of at most 3 here, and one is written with 4');

        QuantifierLimit::check($query, 3);
    }

    /**
     * @throws GqlException
     */
    public function testCheckLeavesAQueryThatMatchesNothingAlone(): void
    {
        $query = Query::of(new QueryBlock([
            new LetClause([new VariableBinding('n', new LiteralExpression(new IntegerDatum(1)))]),
            new ReturnClause(),
        ]));

        $this->expectNotToPerformAssertions();

        QuantifierLimit::check($query, 0);
    }

    /**
     * @throws GqlException
     */
    public function testCheckTermsLeavesAPathThatRepeatsNothingAlone(): void
    {
        $this->expectNotToPerformAssertions();

        QuantifierLimit::checkTerms([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], 0);
    }

    /**
     * @throws GqlException
     */
    public function testCheckTermsRefusesAGroupRepeatedBeyondTheLimit(): void
    {
        $group = new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], new Quantifier(1, 4));

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quantifier may be written with an upper bound of at most 3 here, and one is written with 4');

        QuantifierLimit::checkTerms([$group], 3);
    }

    /**
     * @throws GqlException
     */
    public function testCheckTermsRefusesAQuantifierBeyondTheLimitInsideAGroup(): void
    {
        $group = new GroupPattern([
            new NodePattern('a'),
            new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(2, 6)),
            new NodePattern('b'),
        ]);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a quantifier may be written with an upper bound of at most 3 here, and one is written with 6');

        QuantifierLimit::checkTerms([$group], 3);
    }
}
