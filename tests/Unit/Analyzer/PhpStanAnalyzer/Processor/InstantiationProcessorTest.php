<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\InstantiationProcessor;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(InstantiationProcessor::class)]
#[Medium]
final class InstantiationProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsAClassBeingInstantiated(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/MethodBody.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass', CollectorRun::edgeDescriptions($collected));
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass', CollectorRun::edgeDescriptions($collected));
    }
}
