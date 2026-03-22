<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\DebugAnalyzer\Provider\AtomicProvider;
use App\Analyzer\DebugAnalyzer\Provider\ComponentProvider;
use App\Analyzer\DebugAnalyzer\Provider\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Provider\GraphProvider;
use App\Analyzer\DebugAnalyzer\Provider\PrimitivesProvider;
use Faker;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GraphProviderTest extends TestCase
{
    #[Test]
    #[DoesNotPerformAssertions]
    public function testGraph(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Faker\Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));
        $faker->addProvider(new GraphProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->graph(3);
        }
    }
}
