<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\ClassLikeProcessor;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(ClassLikeProcessor::class)]
#[Medium]
final class ClassLikeProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsWhatAClassLikeIsBuiltFrom(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-implements]-> Tests\Fixture\Source\MyInterface', CollectorRun::edgeDescriptions($collected));
    }

    public function testDeclaredNodeBuildsTheNodeForTheDeclarationItself(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\MyTrait', CollectorRun::nodeNames($collected));
    }

    public function testInheritanceRecordsOnlyWhatTheKindOfDeclarationCanTakeOn(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains(
            'Tests\Fixture\Source\MyInterface -[declaration-trait-use]-> Tests\Fixture\Source\MyTrait',
            CollectorRun::edgeDescriptions($collected),
        );
    }

    public function testProcessRecordsNothingForSourcesWithNoSuchRelation(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-implements]-> Tests\Fixture\Source\MyInterface', CollectorRun::edgeDescriptions($collected));
    }
}
