<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Argument;

use App\Gql\Argument\TextArgument;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TextArgument::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class TextArgumentTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testOfReadsAStringAsItsCharacters(): void
    {
        self::assertSame('App', TextArgument::of(new StringDatum('App')));
    }

    /**
     * @throws GqlException
     */
    public function testOfReportsAValueThatIsNotAString(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('a string was expected, and a INT64 was given');

        TextArgument::of(new IntegerDatum(1));
    }

    /**
     * @throws GqlException
     */
    public function testOfReportsTheAbsenceOfAValueAsNotAString(): void
    {
        $this->expectException(GqlException::class);

        TextArgument::of(new NullDatum());
    }
}
