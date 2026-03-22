<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\DebugAnalyzer\Provider\GraphGenerator;
use App\Analyzer\DebugAnalyzer\Provider\PrimitivesProvider;
use Faker\Factory;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PrimitivesProviderTest extends TestCase
{
    #[Test]
    #[DoesNotPerformAssertions]
    public function testPascalCase(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->pascalCase();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testCamelCase(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->camelCase();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testUpperSnakeCase(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->upperSnakeCase();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testNamespace(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->namespace();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testClassName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->className();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testInterfaceName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->interfaceName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testTraitName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->traitName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testEnumName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->enumName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testMethodName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->methodName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testPropertyName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->propertyName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testConstantName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->constantName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testFunctionName(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->functionName();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testPhpFilename(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->phpFilename();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testPhpFilePath(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->phpFilePath();
        }
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testArray(): void
    {
        /** @var GraphGenerator $faker */
        $faker = Factory::create();
        $faker->addProvider(new PrimitivesProvider($faker));

        for ($i = 0; $i < 100; ++$i) {
            $faker->seed(random_int(1, PHP_INT_MAX));
            $faker->array(fn () => $faker->word());
        }
    }
}
