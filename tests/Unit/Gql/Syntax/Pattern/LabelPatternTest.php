<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LabelPattern::class)]
#[UsesClass(LabelOperator::class)]
#[Small]
final class LabelPatternTest extends TestCase
{
    public function testNamedRequiresOneLabel(): void
    {
        $required = LabelPattern::named('Method');

        self::assertSame(LabelOperator::Named, $required->operator);
        self::assertSame('Method', $required->name);
    }

    public function testAnythingRequiresOnlyThatSomethingIsLabelled(): void
    {
        self::assertSame(LabelOperator::Anything, LabelPattern::anything()->operator);
    }

    public function testBothRequiresTwoThings(): void
    {
        $required = LabelPattern::both(LabelPattern::named('Member'), LabelPattern::named('Callable'));

        self::assertSame(LabelOperator::Both, $required->operator);
        self::assertCount(2, $required->operands);
    }

    public function testEitherRequiresOneOfTwoThings(): void
    {
        $required = LabelPattern::either(LabelPattern::named('Method'), LabelPattern::named('Function'));

        self::assertSame(LabelOperator::Either, $required->operator);
        self::assertCount(2, $required->operands);
    }

    public function testNeitherRefusesOneThing(): void
    {
        $required = LabelPattern::neither(LabelPattern::named('Interface'));

        self::assertSame(LabelOperator::Neither, $required->operator);
        self::assertCount(1, $required->operands);
    }

    public function testARequirementThatNamesNoLabelCarriesNoName(): void
    {
        self::assertNull(LabelPattern::anything()->name);
    }
}
