<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Evaluation;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Evaluation\TextOperation;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TextOperation::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class TextOperationTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testConcatenateJoinsTwoStringsIntoALongerOne(): void
    {
        self::assertEquals(new StringDatum('ab'), TextOperation::concatenate(new StringDatum('a'), new StringDatum('b')));
    }

    /**
     * @throws GqlException
     */
    public function testConcatenateJoinsTwoListsIntoALongerOne(): void
    {
        self::assertEquals(
            new ListDatum([new IntegerDatum(1), new IntegerDatum(2)]),
            TextOperation::concatenate(new ListDatum([new IntegerDatum(1)]), new ListDatum([new IntegerDatum(2)])),
        );
    }

    /**
     * @throws GqlException
     */
    public function testConcatenateAnswersJoiningToTheAbsenceOfAValueWithTheAbsenceOfOne(): void
    {
        self::assertEquals(new NullDatum(), TextOperation::concatenate(new StringDatum('a'), new NullDatum()));
    }

    /**
     * @throws GqlException
     */
    public function testConcatenateAnswersJoiningTheAbsenceOfAValueToAnythingWithTheAbsenceOfOne(): void
    {
        self::assertEquals(new NullDatum(), TextOperation::concatenate(new NullDatum(), new ListDatum([])));
    }

    /**
     * @throws GqlException
     */
    public function testConcatenateReportsAStringJoinedToANumber(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: || joins two strings or two lists, and was given STRING and INT64');

        TextOperation::concatenate(new StringDatum('line '), new IntegerDatum(12));
    }

    /**
     * @throws GqlException
     */
    public function testConcatenateReportsAListJoinedToAString(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[22G03] error: data exception - invalid value type: || joins two strings or two lists, and was given LIST and STRING');

        TextOperation::concatenate(new ListDatum([new IntegerDatum(1)]), new StringDatum('x'));
    }
}
