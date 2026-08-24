<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\ScratchDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ScratchDirectory::class)]
#[Small]
final class ScratchDirectoryTest extends TestCase
{
    public function testCreateMakesADirectoryThatExists(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');

        self::assertDirectoryExists($scratch->path);

        $scratch->delete();
    }

    public function testCreateMakesADirectoryCarryingTheGivenPrefix(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');

        self::assertStringStartsWith('peq-test-', basename($scratch->path));

        $scratch->delete();
    }

    public function testCreateMakesADifferentDirectoryEachTime(): void
    {
        $first = ScratchDirectory::create('peq-test-');
        $second = ScratchDirectory::create('peq-test-');

        self::assertNotSame($first->path, $second->path);

        $first->delete();
        $second->delete();
    }

    public function testWriteReturnsThePathOfWhatItWrote(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');
        $file = $scratch->write('phpstan.neon', "parameters:\n    level: 0\n");

        self::assertSame($scratch->path.'/phpstan.neon', $file);

        $scratch->delete();
    }

    public function testWritePutsTheGivenContentsIntoTheFile(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');
        $file = $scratch->write('phpstan.neon', "parameters:\n    level: 0\n");

        self::assertStringEqualsFile($file, "parameters:\n    level: 0\n");

        $scratch->delete();
    }

    public function testDeleteRemovesTheDirectoryAndWhatWasWrittenIntoIt(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');
        $scratch->write('phpstan.neon', 'parameters: []');
        $path = $scratch->path;

        $scratch->delete();

        self::assertDirectoryDoesNotExist($path);
    }

    public function testDeleteRemovesWhatIsNestedBelowTheDirectory(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');
        mkdir($scratch->path.'/tmp/cache', 0o777, true);
        file_put_contents($scratch->path.'/tmp/cache/compiled.php', '<?php');
        $path = $scratch->path;

        $scratch->delete();

        self::assertDirectoryDoesNotExist($path);
    }

    public function testDeleteIsSafeToRunOnADirectoryThatIsAlreadyGone(): void
    {
        $scratch = ScratchDirectory::create('peq-test-');
        $scratch->delete();
        $scratch->delete();

        self::assertDirectoryDoesNotExist($scratch->path);
    }
}
