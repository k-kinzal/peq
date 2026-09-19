<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\PhpFileCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpFileCollector::class)]
#[Small]
final class PhpFileCollectorTest extends TestCase
{
    public function testCollectFindsThePhpFilesUnderADirectory(): void
    {
        $files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/Fixture/Sample']);

        self::assertContains(realpath(dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php'), $files);
    }

    public function testCollectAcceptsASingleFileAsWellAsADirectory(): void
    {
        $file = dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php';

        self::assertSame([realpath($file)], (new PhpFileCollector())->collect([$file]));
    }

    public function testCollectReportsAbsolutePaths(): void
    {
        $files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/Fixture/Sample']);

        self::assertNotEmpty($files);
        self::assertSame($files, array_values(array_filter($files, static fn (string $file): bool => str_starts_with($file, '/'))));
    }

    public function testCollectReportsEachFileOnlyOnce(): void
    {
        $file = dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php';
        $files = (new PhpFileCollector())->collect([$file, $file]);

        self::assertCount(1, $files);
    }

    public function testCollectLeavesOutWhatTheExcludePatternsFilter(): void
    {
        $files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/Fixture'], [], ['Sample']);

        self::assertNotContains(realpath(dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php'), $files);
    }

    public function testCollectKeepsOnlyWhatTheIncludePatternsSelect(): void
    {
        $files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/Fixture'], ['Sample'], []);

        self::assertNotContains(realpath(dirname(__DIR__, 2).'/Fixture/Source/Comprehensive.php'), $files);
    }

    public function testCollectReportsNothingForAPathThatDoesNotExist(): void
    {
        self::assertSame([], (new PhpFileCollector())->collect([__DIR__.'/nonexistent']));
    }

    public function testCollectReportsNothingWhenGivenNoPaths(): void
    {
        self::assertSame([], (new PhpFileCollector())->collect([]));
    }

    public function testCollectLeavesOutWhatAPathPatternMatches(): void
    {
        $files = (new PhpFileCollector())->collect([dirname(__DIR__, 2).'/Fixture'], [], ['#^Sample/#']);

        self::assertNotContains(realpath(dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php'), $files);
        self::assertContains(realpath(dirname(__DIR__, 2).'/Fixture/Source/ClassDependency.php'), $files);
    }

    public function testCollectKeepsReadingPathsAfterADirectory(): void
    {
        $files = (new PhpFileCollector())->collect([
            dirname(__DIR__, 2).'/Fixture/Source',
            dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php',
        ]);

        self::assertContains(realpath(dirname(__DIR__, 2).'/Fixture/Sample/ComplexSample.php'), $files);
        self::assertContains(realpath(dirname(__DIR__, 2).'/Fixture/Source/ClassDependency.php'), $files);
    }
}
