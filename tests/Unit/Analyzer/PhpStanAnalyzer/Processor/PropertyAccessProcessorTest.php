<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\PropertyAccessProcessor;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(PropertyAccessProcessor::class)]
#[Medium]
final class PropertyAccessProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsAPropertyBeingReadOnThis(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/UsageProcessors.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageProcessorFixture::myProp', CollectorRun::edgeDescriptions($collected));
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains('Tests\Fixture\Source\UsageProcessorFixture::testMethod -[property-access]-> Tests\Fixture\Source\UsageProcessorFixture::myProp', CollectorRun::edgeDescriptions($collected));
    }
}
