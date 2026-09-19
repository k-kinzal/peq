<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\WrittenAutoloadMaps;

/**
 * @internal
 */
#[CoversClass(AutoloadIndex::class)]
#[Small]
final class AutoloadIndexTest extends TestCase
{
    public function testKnowsAClassPhpItselfDeclares(): void
    {
        self::assertTrue(AutoloadIndex::at(dirname(__DIR__, 4))->knows('RuntimeException'));
    }

    public function testKnowsAnInterfacePhpItselfDeclares(): void
    {
        self::assertTrue(AutoloadIndex::at(dirname(__DIR__, 4))->knows('Countable'));
    }

    public function testKnowsAClassTheProjectCanAutoload(): void
    {
        self::assertTrue(AutoloadIndex::at(dirname(__DIR__, 4))->knows('PhpCsFixer\Console\Application'));
    }

    public function testKnowsNoClassNothingDeclares(): void
    {
        self::assertFalse(AutoloadIndex::at(dirname(__DIR__, 4))->knows('Vendor\NotInstalled\Missing'));
    }

    public function testAtReadsNothingBeyondPhpWhereThereAreNoMaps(): void
    {
        self::assertFalse(AutoloadIndex::at(sys_get_temp_dir())->knows('PhpCsFixer\Console\Application'));
    }

    public function testAtStillFindsWhatPhpDeclaresWhereThereAreNoMaps(): void
    {
        self::assertTrue(AutoloadIndex::at(sys_get_temp_dir())->knows('RuntimeException'));
    }

    public function testReadFindsNothingInAMapThatWasNeverWritten(): void
    {
        self::assertSame([], AutoloadIndex::read(sys_get_temp_dir().'/peq-no-such-map.php'));
    }

    public function testReadFindsWhatAMapSays(): void
    {
        $map = sys_get_temp_dir().'/peq-autoload-map-test.php';
        file_put_contents($map, "<?php\n\nreturn ['App\\\\Written' => '/project/Written.php'];\n");

        self::assertSame(['App\Written' => '/project/Written.php'], AutoloadIndex::read($map));
    }

    public function testKnowsAClassTheClassMapNames(): void
    {
        $project = WrittenAutoloadMaps::writeTo(['Written\Mapped' => '/anywhere/Mapped.php'], [], []);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Mapped'));
    }

    public function testKnowsAClassANamespacePrefixLeadsTo(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Deep/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', ['Written\\' => [WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Deep\Reached'));
    }

    public function testKnowsNoClassANamespacePrefixLeadsNowhereFor(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Deep/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', ['Written\\' => [WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertFalse(AutoloadIndex::at($project)->knows('Written\Deep\Missing'));
    }

    public function testKnowsNoClassNoPrefixLeadsTo(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Deep/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', ['Other\\' => [WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertFalse(AutoloadIndex::at($project)->knows('Written\Deep\Reached'));
    }

    public function testKnowsAClassAnOlderStylePrefixLeadsTo(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Deep/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_namespaces.php', ['Written\\' => [WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Deep\Reached'));
    }

    public function testKnowsAClassTheLongestMatchingPrefixLeadsTo(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', [
            'Written\\' => ['/nowhere'],
            'Written\Deep\\' => [WrittenAutoloadMaps::sourceDirectory($project)],
        ]);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Deep\Reached'));
    }

    public function testKnowsAClassTheSecondDirectoryOfAPrefixLeadsTo(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', ['Written\\' => ['/nowhere', WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Reached'));
    }

    public function testReadsPastAPrefixThatIsNotAName(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', [7 => ['/nowhere'], 'Written\\' => [WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Reached'));
    }

    public function testReadsPastAPrefixThatLeadsSomewhereUnreadable(): void
    {
        $project = WrittenAutoloadMaps::writeTo([], [], [], ['src/Reached.php']);
        WrittenAutoloadMaps::write($project.'/vendor/composer/autoload_psr4.php', ['Elsewhere\\' => 'not-a-list', 'Written\\' => [WrittenAutoloadMaps::sourceDirectory($project)]]);

        self::assertTrue(AutoloadIndex::at($project)->knows('Written\Reached'));
    }

    public function testReadLeavesOutWhatIsNeitherAPathNorAListOfThem(): void
    {
        $map = sys_get_temp_dir().'/peq-autoload-odd-map.php';
        WrittenAutoloadMaps::write($map, ['Written\Mapped' => '/anywhere/Mapped.php', 'Written\Odd' => 7, 'Written\Listed' => ['/one', 9]]);

        self::assertSame(['Written\Mapped' => '/anywhere/Mapped.php', 'Written\Listed' => ['/one']], AutoloadIndex::read($map));
    }

    public function testReadsNothingFromAMapThatIsNotOne(): void
    {
        $map = sys_get_temp_dir().'/peq-autoload-not-a-map.php';
        file_put_contents($map, "<?php\n\nreturn 'not a map';\n");

        self::assertSame([], AutoloadIndex::read($map));
    }
}
