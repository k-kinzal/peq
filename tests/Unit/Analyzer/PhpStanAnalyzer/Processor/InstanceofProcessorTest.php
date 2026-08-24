<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\InstanceofProcessor;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(InstanceofProcessor::class)]
#[Medium]
final class InstanceofProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsTheClassAnInstanceofTestNames(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Guards.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\GuardedClass::guarded -[instanceof]-> Tests\Fixture\Source\GuardedClass', CollectorRun::edgeDescriptions($collected));
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains('Tests\Fixture\Source\GuardedClass::guarded -[instanceof]-> Tests\Fixture\Source\GuardedClass', CollectorRun::edgeDescriptions($collected));
    }
}
