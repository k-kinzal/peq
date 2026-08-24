<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\FunctionLikeProcessor;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(FunctionLikeProcessor::class)]
#[Medium]
final class FunctionLikeProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsAMethodAClassDeclares(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod', CollectorRun::edgeDescriptions($collected));
    }

    public function testDeclaringNodeNamesTheKindOfClassLikeAMethodIsDeclaredIn(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Comprehensive.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains(
            'Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod',
            CollectorRun::edgeDescriptions($collected),
        );
    }

    public function testSignatureRecordsTheTypesADeclarationCommitsTo(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/Guards.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains(
            'Tests\Fixture\Source\GuardedClass::guardedBy -[declaration-type-parameter]-> Tests\Fixture\Source\Guard',
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

        self::assertNotContains('Tests\Fixture\Source\ComprehensiveClass -[declaration-method]-> Tests\Fixture\Source\ComprehensiveClass::myMethod', CollectorRun::edgeDescriptions($collected));
    }
}
