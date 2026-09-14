<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\DebugAnalyzer\Generator\FakerRandomSource;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\DebugAnalyzer\SeededGenerators;

/**
 * @internal
 */
#[CoversClass(FakerRandomSource::class)]
#[Small]
final class FakerRandomSourceTest extends TestCase
{
    public function testTheSameSeedReplaysTheSameDraws(): void
    {
        $first = SeededGenerators::random(7);
        $drawn = [$first->numberBetween(0, 1000), $first->boolean(), $first->word()];
        $again = SeededGenerators::random(7);

        self::assertSame($drawn, [$again->numberBetween(0, 1000), $again->boolean(), $again->word()]);
    }

    public function testItDrawsFromTheFakerGeneratorItIsGiven(): void
    {
        $faker = Factory::create();
        $faker->seed(7);
        $expected = [$faker->numberBetween(0, 1000), $faker->boolean(), $faker->word()];
        $faker->seed(7);
        $random = new FakerRandomSource($faker);

        self::assertSame($expected, [$random->numberBetween(0, 1000), $random->boolean(), $random->word()]);
    }
}
