<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use Override;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use Tests\Fixture\Analyzer\CollectorRun;

/**
 * @internal
 */
#[CoversClass(SourceResolver::class)]
#[Medium]
final class SourceResolverTest extends PHPStanTestCase
{
    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [];
    }

    public function testResolveAttributesARelationInsideAMethodToThatMethod(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains(
            'Tests\Fixture\Source\MethodBodyClass::testMethod -[instantiation]-> stdClass',
            CollectorRun::edgeDescriptions($collected),
        );
    }

    public function testResolveAttributesARelationInsideAFunctionToThatFunction(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 3).'/Fixture/Source/UsageProcessors.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertContains('Tests\Fixture\Source\usage_target_func', CollectorRun::nodeNames($collected));
    }

    public function testResolveNamesTheDeclaringSymbolRatherThanTheFile(): void
    {
        $collected = CollectorRun::over(
            new InClassMethodCollector(),
            dirname(__DIR__, 3).'/Fixture/Source/UsageProcessors.php',
            self::getContainer(),
            self::getParser(),
        );

        $descriptions = CollectorRun::edgeDescriptions($collected);
        $fromTheMethod = array_values(array_filter(
            $descriptions,
            static fn (string $description): bool => str_starts_with($description, 'Tests\Fixture\Source\UsageProcessorFixture::'),
        ));

        self::assertNotEmpty($descriptions);
        self::assertSame($descriptions, $fromTheMethod);
    }

    public function testResolveProducesANodeThatReportsItselfAsAnalysed(): void
    {
        $collected = CollectorRun::over(
            new DependencyCollector(),
            dirname(__DIR__, 3).'/Fixture/Source/ClassDependency.php',
            self::getContainer(),
            self::getParser(),
        );

        self::assertSame(NodeKind::Klass, $collected[0]->kind());
    }
}
