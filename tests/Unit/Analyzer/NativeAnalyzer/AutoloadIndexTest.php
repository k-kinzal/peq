<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use org\bovigo\vfs\vfsStream;
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
        self::assertFalse(AutoloadIndex::at(vfsStream::setup('project')->url())->knows('PhpCsFixer\Console\Application'));
    }

    public function testAtStillFindsWhatPhpDeclaresWhereThereAreNoMaps(): void
    {
        self::assertTrue(AutoloadIndex::at(vfsStream::setup('project')->url())->knows('RuntimeException'));
    }

    public function testReadFindsNothingInAMapThatWasNeverWritten(): void
    {
        self::assertSame([], AutoloadIndex::read(vfsStream::setup('project')->url().'/vendor/composer/autoload_classmap.php'));
    }

    public function testReadFindsWhatAMapSays(): void
    {
        $root = vfsStream::setup('project', null, ['autoload_classmap.php' => <<<'PHP'
            <?php

            return ['App\Written' => '/project/Written.php'];
            PHP]);

        self::assertSame(['App\Written' => '/project/Written.php'], AutoloadIndex::read($root->url().'/autoload_classmap.php'));
    }

    public function testKnowsAClassTheClassMapNames(): void
    {
        $root = vfsStream::setup('project', null, ['vendor' => ['composer' => ['autoload_classmap.php' => <<<'PHP'
            <?php

            return ['Written\Mapped' => '/anywhere/Mapped.php'];
            PHP]]]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Mapped'));
    }

    public function testKnowsAClassANamespacePrefixLeadsTo(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return ['Written\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Deep' => ['Reached.php' => "<?php\n"]],
        ]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Deep\Reached'));
    }

    public function testKnowsNoClassANamespacePrefixLeadsNowhereFor(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return ['Written\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Deep' => ['Reached.php' => "<?php\n"]],
        ]);

        self::assertFalse(AutoloadIndex::at($root->url())->knows('Written\Deep\Missing'));
    }

    public function testKnowsNoClassNoPrefixLeadsTo(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return ['Other\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Deep' => ['Reached.php' => "<?php\n"]],
        ]);

        self::assertFalse(AutoloadIndex::at($root->url())->knows('Written\Deep\Reached'));
    }

    public function testKnowsAClassAnOlderStylePrefixLeadsTo(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_namespaces.php' => <<<'PHP'
                <?php

                return ['Written\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Deep' => ['Reached.php' => "<?php\n"]],
        ]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Deep\Reached'));
    }

    public function testKnowsAClassTheLongestMatchingPrefixLeadsTo(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return ['Written\\' => ['/nowhere'], 'Written\Deep\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Reached.php' => "<?php\n"],
        ]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Deep\Reached'));
    }

    public function testKnowsAClassTheSecondDirectoryOfAPrefixLeadsTo(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return ['Written\\' => ['/nowhere', 'vfs://project/src']];
                PHP]],
            'src' => ['Reached.php' => "<?php\n"],
        ]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Reached'));
    }

    public function testReadsPastAPrefixThatIsNotAName(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return [7 => ['/nowhere'], 'Written\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Reached.php' => "<?php\n"],
        ]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Reached'));
    }

    public function testReadsPastAPrefixThatLeadsSomewhereUnreadable(): void
    {
        $root = vfsStream::setup('project', null, [
            'vendor' => ['composer' => ['autoload_psr4.php' => <<<'PHP'
                <?php

                return ['Elsewhere\\' => 'not-a-list', 'Written\\' => ['vfs://project/src']];
                PHP]],
            'src' => ['Reached.php' => "<?php\n"],
        ]);

        self::assertTrue(AutoloadIndex::at($root->url())->knows('Written\Reached'));
    }

    public function testReadLeavesOutWhatIsNeitherAPathNorAListOfThem(): void
    {
        $root = vfsStream::setup('project', null, ['autoload_classmap.php' => <<<'PHP'
            <?php

            return ['Written\Mapped' => '/anywhere/Mapped.php', 'Written\Odd' => 7, 'Written\Listed' => ['/one', 9]];
            PHP]);

        self::assertSame(
            ['Written\Mapped' => '/anywhere/Mapped.php', 'Written\Listed' => ['/one']],
            AutoloadIndex::read($root->url().'/autoload_classmap.php'),
        );
    }

    public function testReadsNothingFromAMapThatIsNotOne(): void
    {
        $root = vfsStream::setup('project', null, ['autoload_classmap.php' => <<<'PHP'
            <?php

            return 'not a map';
            PHP]);

        self::assertSame([], AutoloadIndex::read($root->url().'/autoload_classmap.php'));
    }
}
