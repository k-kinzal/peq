<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\FileMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FileMeta::class)]
#[Small]
final class FileMetaTest extends TestCase
{
    public function testPathIsKeptAsItWasGiven(): void
    {
        self::assertSame('/project/src/Domain/Invoice.php', (new FileMeta('/project/src/Domain/Invoice.php', 10, 5))->path);
    }

    public function testLineAndColumnAreKeptAsTheyWereGiven(): void
    {
        $meta = new FileMeta('/project/src/Domain/Invoice.php', 10, 5);

        self::assertSame(10, $meta->line);
        self::assertSame(5, $meta->column);
    }

    #[DataProvider('providerPathsAndTheirFileNames')]
    public function testNameIsTheLastSegmentOfThePath(string $path, string $expected): void
    {
        self::assertSame($expected, (new FileMeta($path, 1, 1))->name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPathsAndTheirFileNames(): iterable
    {
        yield 'an absolute path' => ['/project/src/Domain/Invoice.php', 'Invoice.php'];

        yield 'a relative path' => ['src/Domain/Invoice.php', 'Invoice.php'];

        yield 'a bare file name' => ['Invoice.php', 'Invoice.php'];

        yield 'a path with no extension' => ['/project/bin/console', 'console'];
    }
}
