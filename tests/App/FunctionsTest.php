<?php

declare(strict_types=1);

namespace Tests\App;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function App\array_flatten;

/**
 * @internal
 */
final class FunctionsTest extends TestCase
{
    #[Test]
    public function testArrayFlatten(): void
    {
        $input = [
            'a' => [1, 2],
            'b' => [3, 4],
            'c' => [],
        ];

        $result = array_flatten($input);

        self::assertSame([1, 2, 3, 4], $result);
    }

    #[Test]
    public function testArrayFlattenWithEmptyArray(): void
    {
        self::assertSame([], array_flatten([]));
    }
}
