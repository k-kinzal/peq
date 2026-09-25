<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Query;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Reporter\Query\EdgeCaption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeCaption::class)]
#[UsesNamespace('App')]
#[Small]
final class EdgeCaptionTest extends TestCase
{
    public function testOfDrawsEachWrittenOccurrenceAndRetainsOrdinaryLabels(): void
    {
        $edge = new EdgeDatum('e', ['functionCall'], [
            'callSite' => new StringDatum('site'), 'expression' => new StringDatum("B(\r\n\t42)"),
            'file' => new StringDatum('/source.php'), 'line' => new IntegerDatum(3), 'column' => new IntegerDatum(5),
        ], 'A', 'B');

        self::assertSame('functionCall: B(\r\n\t42) @ /source.php:3:5', EdgeCaption::of($edge));
        self::assertSame('functionCall', EdgeCaption::of(new EdgeDatum('e', ['functionCall'], [], 'A', 'B')));
        self::assertSame('functionCall', EdgeCaption::of(new EdgeDatum('e', ['functionCall'], ['callSite' => new StringDatum('site')], 'A', 'B')));
    }
}
