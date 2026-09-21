<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Matching;

use App\Gql\Matching\LabelMatching;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LabelMatching::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[Small]
final class LabelMatchingTest extends TestCase
{
    /**
     * @param list<string> $labels
     */
    #[DataProvider('providerRequirementsAndWhetherLabelsSatisfyThem')]
    public function testSatisfiesReportsWhetherLabelsSatisfyARequirement(LabelPattern $required, array $labels, bool $satisfied): void
    {
        self::assertSame($satisfied, LabelMatching::satisfies($required, $labels));
    }

    /**
     * @return iterable<string, array{LabelPattern, list<string>, bool}>
     */
    public static function providerRequirementsAndWhetherLabelsSatisfyThem(): iterable
    {
        yield 'a label the element carries' => [LabelPattern::named('Method'), ['Method', 'Callable'], true];

        yield 'a label it does not' => [LabelPattern::named('Class'), ['Method', 'Callable'], false];

        yield 'a family it belongs to' => [LabelPattern::named('Callable'), ['Method', 'Member', 'Callable'], true];

        yield 'either of two, one of which it carries' => [
            LabelPattern::either(LabelPattern::named('Class'), LabelPattern::named('Method')),
            ['Method'],
            true,
        ];

        yield 'either of two, neither of which it carries' => [
            LabelPattern::either(LabelPattern::named('Class'), LabelPattern::named('Interface')),
            ['Method'],
            false,
        ];

        yield 'both of two, both of which it carries' => [
            LabelPattern::both(LabelPattern::named('Member'), LabelPattern::named('Callable')),
            ['Method', 'Member', 'Callable'],
            true,
        ];

        yield 'both of two, one of which it does not' => [
            LabelPattern::both(LabelPattern::named('Member'), LabelPattern::named('Callable')),
            ['Function', 'Callable'],
            false,
        ];

        yield 'a refusal of a label it does not carry' => [
            LabelPattern::neither(LabelPattern::named('Interface')),
            ['Class', 'ClassLike'],
            true,
        ];

        yield 'a refusal of one it does' => [
            LabelPattern::neither(LabelPattern::named('Interface')),
            ['Interface', 'ClassLike'],
            false,
        ];

        yield 'anything at all, of an element that carries something' => [LabelPattern::anything(), ['Class'], true];

        yield 'anything at all, of an element that carries nothing' => [LabelPattern::anything(), [], false];

        yield 'a family without one of its members' => [
            LabelPattern::both(LabelPattern::named('ClassLike'), LabelPattern::neither(LabelPattern::named('Interface'))),
            ['Class', 'ClassLike'],
            true,
        ];
    }

    public function testSatisfiesIsSatisfiedByAnythingWhenNothingIsRequired(): void
    {
        self::assertTrue(LabelMatching::satisfies(null, []));
    }
}
