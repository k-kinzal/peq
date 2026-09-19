<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WorkingDirectory::class)]
#[Small]
final class WorkingDirectoryTest extends TestCase
{
    public function testSharedIsNamedAfterTheRunningUser(): void
    {
        self::assertSame(sys_get_temp_dir().'/peq-phpstan-'.getmyuid(), WorkingDirectory::shared()->path);
    }

    public function testSharedIsADirectoryThatExists(): void
    {
        self::assertDirectoryExists(WorkingDirectory::shared()->path);
    }

    public function testSharedIsTheSameDirectoryEachTime(): void
    {
        self::assertSame(WorkingDirectory::shared()->path, WorkingDirectory::shared()->path);
    }

    public function testAtMakesADirectoryThatDidNotExist(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());

        self::assertDirectoryExists($directory->path);

        $directory->delete();
    }

    public function testAtMakesTheParentsOfADirectoryAsWell(): void
    {
        $parent = sys_get_temp_dir().'/peq-test-'.uniqid();
        $directory = WorkingDirectory::at($parent.'/nested/deeper');

        self::assertDirectoryExists($parent.'/nested/deeper');

        (new WorkingDirectory($parent))->delete();
    }

    public function testAtKeepsADirectoryThatAlreadyExists(): void
    {
        $first = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $first->write('kept.txt', 'kept');
        $second = WorkingDirectory::at($first->path);

        self::assertSame($first->path, $second->path);
        self::assertSame('kept', file_get_contents($second->path.'/kept.txt'));

        $first->delete();
    }

    public function testWriteReturnsThePathOfWhatItWrote(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $file = $directory->write('config.neon', 'parameters: []');

        self::assertSame($directory->path.'/config.neon', $file);

        $directory->delete();
    }

    public function testWritePutsTheGivenContentsIntoTheFile(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $file = $directory->write('config.neon', 'parameters: []');

        self::assertSame('parameters: []', file_get_contents($file));

        $directory->delete();
    }

    public function testWriteReplacesWhatTheFileHeldBefore(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $directory->write('config.neon', 'first');
        $file = $directory->write('config.neon', 'second');

        self::assertSame('second', file_get_contents($file));

        $directory->delete();
    }

    public function testWriteLeavesOnlyTheFileBehind(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $directory->write('config.neon', 'parameters: []');
        $entries = scandir($directory->path);

        self::assertNotFalse($entries);
        self::assertSame(['config.neon'], array_values(array_diff($entries, ['.', '..'])));

        $directory->delete();
    }

    public function testDeleteRemovesTheDirectoryAndWhatWasWrittenIntoIt(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $file = $directory->write('config.neon', 'parameters: []');
        $directory->delete();

        self::assertFileDoesNotExist($file);
        self::assertDirectoryDoesNotExist($directory->path);
    }

    public function testDeleteRemovesWhatIsNestedBelowTheDirectory(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        WorkingDirectory::at($directory->path.'/nested')->write('inner.txt', 'inner');
        $directory->delete();

        self::assertDirectoryDoesNotExist($directory->path);
    }

    public function testDeleteIsSafeToRunOnADirectoryThatIsAlreadyGone(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        $directory->delete();
        $directory->delete();

        self::assertDirectoryDoesNotExist($directory->path);
    }

    public function testDeleteRemovesWhatFollowsANestedDirectory(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-test-'.uniqid());
        WorkingDirectory::at($directory->path.'/aaa')->write('inner.txt', 'inner');
        $directory->write('zzz.php', '<?php');
        $directory->delete();

        self::assertDirectoryDoesNotExist($directory->path);
    }
}
