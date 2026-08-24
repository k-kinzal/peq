<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\PropertyProcessor;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(PropertyProcessor::class)]
#[Medium]
final class PropertyProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsAPropertyAClassDeclares(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-property]-> Tests\Fixture\Source\ComprehensiveClass::myProp', CollectorRun::edgeDescriptions($collected));
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-property]-> Tests\Fixture\Source\ComprehensiveClass::myProp', CollectorRun::edgeDescriptions($collected));
    }
}
