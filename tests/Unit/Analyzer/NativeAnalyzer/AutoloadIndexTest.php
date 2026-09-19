<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

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
}
