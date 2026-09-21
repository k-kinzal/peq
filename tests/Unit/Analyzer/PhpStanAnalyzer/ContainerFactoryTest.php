<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
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
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'], [DependencyCollector::class, InClassMethodCollector::class]);

        self::assertNotEmpty($container->getServicesByTag('phpstan.collector'));
        self::assertTrue($container->hasParameter('level'));
    }

    public function testCreateRegistersTheCollectorsPeqReadsItsGraphFrom(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'], [DependencyCollector::class, InClassMethodCollector::class]);
        $registered = array_map(
            static fn (object $collector): string => $collector::class,
            array_values(array_filter($container->getServicesByTag('phpstan.collector'), 'is_object')),
        );

        self::assertContains(DependencyCollector::class, $registered);
        self::assertContains(InClassMethodCollector::class, $registered);
    }

    public function testCreateRegistersOnlyTheCollectorsItIsGiven(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'], [InClassMethodCollector::class]);
        $registered = array_map(
            static fn (object $collector): string => $collector::class,
            array_values(array_filter($container->getServicesByTag('phpstan.collector'), 'is_object')),
        );

        self::assertSame([InClassMethodCollector::class], $registered);
    }

    /**
     * @throws ParameterNotFoundException
     */
    public function testCreateHasPhpStanWorkInTheDirectoryTheUserShares(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'], [DependencyCollector::class, InClassMethodCollector::class]);

        self::assertSame(WorkingDirectory::shared()->path, $container->getParameter('tmpDir'));
    }

    /**
     * @throws ParameterNotFoundException
     */
    public function testCreateConfiguresPhpStanFromAFileNamingTheCollectors(): void
    {
        $container = (new ContainerFactory())->create([dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'], [DependencyCollector::class, InClassMethodCollector::class]);
        $configurations = $container->getParameter('additionalConfigFiles');

        self::assertIsArray($configurations);
        self::assertCount(1, $configurations);
        self::assertIsString($configurations[0]);
        self::assertStringStartsWith(WorkingDirectory::shared()->path.'/phpstan-', $configurations[0]);
        self::assertStringContainsString(InClassMethodCollector::class, (string) file_get_contents($configurations[0]));
    }

    /**
     * @throws ParameterNotFoundException
     */
    public function testCreateKeepsOneConfigurationPerSetOfCollectors(): void
    {
        $factory = new ContainerFactory();
        $files = [dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'];
        $first = $factory->create($files, [DependencyCollector::class])->getParameter('additionalConfigFiles');
        $again = $factory->create($files, [DependencyCollector::class])->getParameter('additionalConfigFiles');
        $other = $factory->create($files, [InClassMethodCollector::class])->getParameter('additionalConfigFiles');

        self::assertSame($first, $again);
        self::assertNotSame($first, $other);
    }

    public function testCreateCanBeCalledMoreThanOnce(): void
    {
        $factory = new ContainerFactory();
        $files = [dirname(__DIR__, 3).'/Fixture/Sample/AnalysedSample.php'];

        self::assertNotSame($factory->create($files, [DependencyCollector::class]), $factory->create($files, [DependencyCollector::class]));
    }
}
