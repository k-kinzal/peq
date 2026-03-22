<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\DebugAnalyzer\Provider\AtomicProvider;
use App\Analyzer\DebugAnalyzer\Provider\ComponentProvider;
use App\Analyzer\DebugAnalyzer\Provider\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Provider\PrimitivesProvider;
use Faker\Factory;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ComponentProviderTest extends TestCase
{
    #[Test]
    #[DoesNotPerformAssertions]
    public function testNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->node();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testClassNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->classNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testGraphInterfaceNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->interfaceNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testTraitNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->traitNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testEnumNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->enumNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testMethodNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->methodNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testPropertyNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->propertyNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testFunctionNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->functionNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testConstantNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->constantNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testEnumCaseNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->enumCaseNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testBuiltinNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->builtinNode();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testUnknownNode(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));
        $faker->addProvider(new ComponentProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->unknownNode();
        }
    }
}
