<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use PHPStan\DependencyInjection\ParameterNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ContainerFactory::class)]
#[Medium]
final class ContainerFactoryTest extends TestCase
{
    public function testCreateBuildsAContainerThatCanAnalyse(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php']);

        self::assertNotEmpty($container->getServicesByTag('phpstan.collector'));
        self::assertTrue($container->hasParameter('level'));
    }

    public function testCreateRegistersTheCollectorsPeqReadsItsGraphFrom(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php']);
        $registered = array_map(
            static fn (object $collector): string => $collector::class,
            array_values(array_filter($container->getServicesByTag('phpstan.collector'), 'is_object')),
        );

        self::assertContains(DependencyCollector::class, $registered);
        self::assertContains(InClassMethodCollector::class, $registered);
    }

    /**
     * @throws ParameterNotFoundException
     */
    public function testCreateLeavesNothingBehindOfTheConfigurationItGenerated(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php']);
        $scratch = $container->getParameter('tmpDir');

        self::assertIsString($scratch);
        self::assertStringStartsWith(sys_get_temp_dir().'/peq-phpstan-', $scratch);
        self::assertDirectoryDoesNotExist($scratch);
    }

    public function testCreateCanBeCalledMoreThanOnce(): void
    {
        $factory = new ContainerFactory();
        $files = [dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'];

        self::assertNotSame($factory->create($files), $factory->create($files));
    }
}
