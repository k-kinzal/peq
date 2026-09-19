<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Syntax\Expression\IndexExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IndexExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class IndexExpressionTest extends TestCase
{
    public function testAnIndexingCarriesTheListAndThePlaceInIt(): void
    {
        $subject = new VariableExpression('e');
        $place = new LiteralExpression(new IntegerDatum(0));
        $taken = new IndexExpression($subject, $place);

        self::assertSame($subject, $taken->subject);
        self::assertSame($place, $taken->index);
    }
}
