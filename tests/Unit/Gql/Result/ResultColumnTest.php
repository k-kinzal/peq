<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Result;

use App\Gql\Result\ResultColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResultColumn::class)]
#[Small]
final class ResultColumnTest extends TestCase
{
    public function testAColumnCarriesWhatItIsCalledAndWhatItHolds(): void
    {
        $column = new ResultColumn('n', 'INT64');

        self::assertSame('n', $column->heading);
        self::assertSame('INT64', $column->type);
    }
}
