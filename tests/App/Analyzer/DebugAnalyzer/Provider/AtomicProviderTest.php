<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\DebugAnalyzer\Provider\AtomicProvider;
use App\Analyzer\DebugAnalyzer\Provider\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Provider\PrimitivesProvider;
use Faker\Factory;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AtomicProviderTest extends TestCase
{
    #[Test]
    #[DoesNotPerformAssertions]
    public function testNodeKind(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->nodeKind();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testEdgeKind(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->edgeKind();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->nodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testClassNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->classNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testInterfaceNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->interfaceNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testTraitNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->traitNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testEnumNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->enumNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testMethodNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->methodNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testPropertyNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->propertyNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testFunctionNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->functionNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testConstantNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->constantNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testEnumCaseNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->enumCaseNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testBuiltinNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->builtinNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testUnknownNodeId(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->unknownNodeId();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testFileMeta(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));
        $faker->addProvider(new AtomicProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->fileMeta();
        }
    }
}
