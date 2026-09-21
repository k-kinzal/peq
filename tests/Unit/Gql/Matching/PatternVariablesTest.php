<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Matching\PatternVariables;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PatternVariables::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[Small]
final class PatternVariablesTest extends TestCase
{
    /**
     * @param list<string> $names
     */
    #[DataProvider('providerPatternsAndTheNamesTheyBind')]
    public function testOfReturnsEveryNameAPatternWouldBind(GraphPattern $pattern, array $names): void
    {
        self::assertSame($names, PatternVariables::of($pattern));
    }

    /**
     * @return iterable<string, array{GraphPattern, list<string>}>
     */
    public static function providerPatternsAndTheNamesTheyBind(): iterable
    {
        yield 'the names written in a path, the path itself first' => [
            new GraphPattern([
                new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e'), new NodePattern('b')], PathMode::Walk, 'p'),
            ]),
            ['p', 'a', 'e', 'b'],
        ];

        yield 'a pattern that binds nothing' => [
            new GraphPattern([
                new PathPattern([new NodePattern(), new EdgePattern(EdgeDirection::Along), new NodePattern()], PathMode::Walk),
            ]),
            [],
        ];

        yield 'a name written twice, bound once' => [
            new GraphPattern([
                new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('a')], PathMode::Walk),
            ]),
            ['a'],
        ];

        yield 'the names of every path matched together' => [
            new GraphPattern([
                new PathPattern([new NodePattern('a')], PathMode::Walk),
                new PathPattern([new NodePattern('b')], PathMode::Walk),
            ]),
            ['a', 'b'],
        ];

        yield 'the names inside a parenthesised stretch of pattern' => [
            new GraphPattern([
                new PathPattern(
                    [new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e'), new NodePattern('b')], new Quantifier(1, 3))],
                    PathMode::Walk,
                ),
            ]),
            ['a', 'e', 'b'],
        ];
    }

    public function testGroupListsReadsTheNameARepetitionBindsToEveryRelationItCrossed(): void
    {
        $pattern = new GraphPattern([
            new PathPattern(
                [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 3)), new NodePattern('b')],
                PathMode::Walk,
            ),
        ]);

        self::assertSame(['e'], PatternVariables::groupLists($pattern));
    }

    public function testGroupListsReadsNoNameFromARelationCrossedOnce(): void
    {
        $pattern = new GraphPattern([
            new PathPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along, 'e'), new NodePattern('b')], PathMode::Walk),
        ]);

        self::assertSame([], PatternVariables::groupLists($pattern));
    }

    public function testGroupListsReadsNoNameFromARepetitionThatBindsNone(): void
    {
        $pattern = new GraphPattern([
            new PathPattern(
                [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, null, null, new ElementFilter(), new Quantifier(1, 3)), new NodePattern('b')],
                PathMode::Walk,
            ),
        ]);

        self::assertSame([], PatternVariables::groupLists($pattern));
    }

    public function testGroupListsReadsEachNameOnceAcrossEveryPath(): void
    {
        $repeated = new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 3));
        $pattern = new GraphPattern([
            new PathPattern([new NodePattern('a'), $repeated, new NodePattern('b')], PathMode::Walk),
            new PathPattern([new NodePattern('b'), $repeated, new NodePattern('c')], PathMode::Walk),
        ]);

        self::assertSame(['e'], PatternVariables::groupLists($pattern));
    }

    public function testRepeatedInSearchesInsideAParenthesisedStretchOfPattern(): void
    {
        $group = new GroupPattern([new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 3))]);

        self::assertSame(['e'], PatternVariables::repeatedIn([$group]));
    }

    public function testRepeatedInReadsNoNameFromAPatternWithNoPiecesAtAll(): void
    {
        self::assertSame([], PatternVariables::repeatedIn([]));
    }

    public function testInTermsReturnsTheNamesInsideAParenthesisedStretchOfPattern(): void
    {
        self::assertSame(['a'], PatternVariables::inTerms([new GroupPattern([new NodePattern('a')])]));
    }

    public function testInTermsReturnsNothingForAPatternWithNoPiecesAtAll(): void
    {
        self::assertSame([], PatternVariables::inTerms([]));
    }
}
