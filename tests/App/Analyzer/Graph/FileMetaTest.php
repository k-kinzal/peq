<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph;

use App\Analyzer\Graph\FileMeta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FileMetaTest extends TestCase
{
    #[Test]
    public function testConstructorSetsProperties(): void
    {
        $fileMeta = new FileMeta('/path/to/MyClass.php', 10, 5);

        self::assertSame('/path/to/MyClass.php', $fileMeta->path);
        self::assertSame(10, $fileMeta->line);
        self::assertSame(5, $fileMeta->column);
    }

    #[Test]
    public function testConstructorExtractsFilename(): void
    {
        $fileMeta = new FileMeta('/path/to/MyClass.php', 10, 5);

        self::assertSame('MyClass.php', $fileMeta->name);
    }

    #[Test]
    public function testConstructorExtractsFilenameWithoutDirectory(): void
    {
        $fileMeta = new FileMeta('MyClass.php', 10, 5);

        self::assertSame('MyClass.php', $fileMeta->name);
    }

    #[Test]
    public function testConstructorExtractsFilenameFromNestedPath(): void
    {
        $fileMeta = new FileMeta('/very/deep/path/to/MyClass.php', 42, 15);

        self::assertSame('MyClass.php', $fileMeta->name);
    }

    #[Test]
    public function testConstructorAssertsLineIsPositive(): void
    {
        $this->expectException(\AssertionError::class);
        new FileMeta('/path/to/file.php', 0, 1);
    }

    #[Test]
    public function testConstructorAssertsColumnIsPositive(): void
    {
        $this->expectException(\AssertionError::class);
        new FileMeta('/path/to/file.php', 1, 0);
    }
}
