<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Syntax\Expression\LiteralExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LiteralExpression::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(DatumKind::class)]
#[Small]
final class LiteralExpressionTest extends TestCase
{
    public function testALiteralCarriesTheValueTheQueryWrote(): void
    {
        $value = new IntegerDatum(42);

        self::assertSame($value, (new LiteralExpression($value))->value);
    }

    public function testALiteralHoldsTheValueRatherThanTheTextThatWroteIt(): void
    {
        self::assertSame(DatumKind::Integer, (new LiteralExpression(new IntegerDatum(42)))->value->kind());
    }
}
