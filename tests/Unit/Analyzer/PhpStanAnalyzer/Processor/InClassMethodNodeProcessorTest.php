<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\Processor\InClassMethodNodeProcessor;
use Override;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(InClassMethodNodeProcessor::class)]
#[Medium]
final class InClassMethodNodeProcessorTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testProcessRecordsWhatAMethodBodyReaches(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/MethodBody.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass', CollectorRun::edgeDescriptions($collected));
    }

    public function testHandlesSelectsTheExpressionsDispatchHasAnArmFor(): void
    {
        self::assertTrue(InClassMethodNodeProcessor::handles(new New_(new Name('stdClass'))));
        self::assertFalse(InClassMethodNodeProcessor::handles(new Variable('unrelated')));
    }

    public function testDispatchReportsNothingForAnExpressionWithNoArm(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 4).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertSame([], $collected);
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
