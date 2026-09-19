<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\LabelOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LabelOperator::class)]
#[Small]
final class LabelOperatorTest extends TestCase
{
    public function testTheOperatorsAreTheOnesGqlWritesLabelExpressionsWith(): void
    {
        self::assertSame(
            [
                LabelOperator::Named,
                LabelOperator::Anything,
                LabelOperator::Both,
                LabelOperator::Either,
                LabelOperator::Neither,
            ],
            LabelOperator::cases(),
        );
    }
}
