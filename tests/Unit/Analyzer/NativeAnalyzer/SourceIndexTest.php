<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use org\bovigo\vfs\vfsStream;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceIndex::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(AutoloadIndex::class)]
#[UsesClass(ClassLikeDeclaration::class)]
#[UsesClass(ParsedSource::class)]
#[Small]
final class SourceIndexTest extends TestCase
{
    public function testClassLikeFindsWhatTheAnalysedFilesDeclare(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\ntrait Shared {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertSame(NodeKind::Trait, $index->classLike('App\Shared')?->kind);
    }

    public function testClassLikeFindsADeclarationWhateverCasingItIsAskedFor(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertSame('App\Invoice', $index->classLike('app\invoice')?->name);
    }

    public function testClassLikeFindsNothingForASymbolNoAnalysedFileWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertNull($index->classLike('App\Money'));
    }

    public function testOfKeepsTheDeclarationOfTheFileReadFirstWhenTwoNameTheSameSymbol(): void
    {
        $root = vfsStream::setup('project', null, [
            'First.php' => "<?php\nnamespace App;\nclass Invoice {}\n",
            'Second.php' => "<?php\nnamespace App;\nclass Invoice {}\n",
        ]);
        $index = SourceIndex::of([$root->url().'/First.php', $root->url().'/Second.php'], $root->url());

        self::assertSame('vfs://project/First.php', $index->classLike('App\Invoice')?->source->path);
    }

    public function testDeclaresFunctionFindsTheFunctionsTheAnalysedFilesDeclare(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertTrue($index->declaresFunction('App\helper'));
    }

    public function testDeclaresFunctionFindsNoFunctionNoAnalysedFileDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertFalse($index->declaresFunction('App\missing'));
    }

    public function testSourcesAreReadInTheOrderTheyAreGiven(): void
    {
        $root = vfsStream::setup('project', null, ['First.php' => "<?php\nclass One {}\n", 'Second.php' => "<?php\nclass Two {}\n"]);
        $index = SourceIndex::of([$root->url().'/First.php', $root->url().'/Second.php'], $root->url());

        self::assertSame(
            ['vfs://project/First.php', 'vfs://project/Second.php'],
            array_map(static fn (ParsedSource $source): string => $source->path, $index->sources()),
        );
    }

    public function testSourceOfFindsAnAnalysedFileByItsPath(): void
    {
        $root = vfsStream::setup('project', null, ['Only.php' => "<?php\nclass One {}\n"]);
        $index = SourceIndex::of([$root->url().'/Only.php'], $root->url());

        self::assertSame('vfs://project/Only.php', $index->sourceOf('vfs://project/Only.php')?->path);
    }

    public function testSourceOfFindsNothingForAPathTheAnalysisWasNotGiven(): void
    {
        $root = vfsStream::setup('project', null, ['Only.php' => "<?php\nclass One {}\n"]);
        $index = SourceIndex::of([$root->url().'/Only.php'], $root->url());

        self::assertNull($index->sourceOf('/nowhere/Only.php'));
    }

    public function testOfLeavesOutAFilePhpItselfWouldRefuse(): void
    {
        $root = vfsStream::setup('project', null, [
            'Broken.php' => "<?php\nnamespace Bracketed { class InBrackets {} }\nclass Unbracketed {}\n",
            'Intact.php' => "<?php\nnamespace App;\nclass Survivor {}\n",
        ]);
        $index = SourceIndex::of([$root->url().'/Broken.php', $root->url().'/Intact.php'], $root->url());

        self::assertSame(['vfs://project/Intact.php'], array_map(static fn (ParsedSource $source): string => $source->path, $index->sources()));
    }

    public function testOfStillReadsTheFilesAroundOnePhpWouldRefuse(): void
    {
        $root = vfsStream::setup('project', null, [
            'Broken.php' => "<?php\nnamespace Bracketed { class InBrackets {} }\nclass Unbracketed {}\n",
            'Intact.php' => "<?php\nnamespace App;\nclass Survivor {}\n",
        ]);
        $index = SourceIndex::of([$root->url().'/Broken.php', $root->url().'/Intact.php'], $root->url());

        self::assertSame('App\Survivor', $index->classLike('App\Survivor')?->name);
    }

    public function testOfLeavesOutAFileItCannotRead(): void
    {
        $root = vfsStream::setup('project');

        self::assertSame([], SourceIndex::of([$root->url().'/Missing.php'], $root->url())->sources());
    }

    public function testKnowsClassFindsWhatAnAnalysedFileDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertTrue($index->knowsClass('App\Invoice'));
    }

    public function testKnowsClassFindsWhatPhpItselfDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nclass One {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertTrue($index->knowsClass('RuntimeException'));
    }

    public function testKnowsClassFindsNothingWhereNothingDeclaresIt(): void
    {
        $root = vfsStream::setup('project', null, ['Declared.php' => "<?php\nclass One {}\n"]);
        $index = SourceIndex::of([$root->url().'/Declared.php'], $root->url());

        self::assertFalse($index->knowsClass('Vendor\NotInstalled\Missing'));
    }

    public function testRelativePathIsWrittenFromWhereTheAnalysisRuns(): void
    {
        self::assertSame('src/Invoice.php', SourceIndex::relativePath('/project/src/Invoice.php', '/project'));
    }

    public function testRelativePathOfAFileOutsideTheAnalysisIsWrittenInFull(): void
    {
        self::assertSame('/elsewhere/Invoice.php', SourceIndex::relativePath('/elsewhere/Invoice.php', '/project'));
    }

    public function testRelativePathIsWrittenTheSameWayWhateverSeparatorItUses(): void
    {
        self::assertSame('src/Invoice.php', SourceIndex::relativePath('\project\src\Invoice.php', '\project'));
    }

    public function testRelativePathOfAnIndexedFileIsWrittenFromWhereTheAnalysisRuns(): void
    {
        $root = vfsStream::setup('project', null, ['src' => ['Only.php' => "<?php\nclass One {}\n"]]);
        $index = SourceIndex::of([$root->url().'/src/Only.php'], $root->url());

        self::assertSame('src/Only.php', $index->relativePathOf('vfs://project/src/Only.php'));
    }

    public function testParseReadsAFileWithEveryWrittenNameResolved(): void
    {
        $root = vfsStream::setup('project', null, ['Only.php' => "<?php\nnamespace App;\nuse Vendor\\Base;\nclass One extends Base {}\n"]);
        $statements = SourceIndex::parse((new ParserFactory())->createForHostVersion(), $root->url().'/Only.php') ?? [];

        $class = (new NodeFinder())->findFirstInstanceOf($statements, Class_::class);

        self::assertSame(['App\One', 'Vendor\Base'], [$class?->namespacedName?->toString(), $class?->extends?->toString()]);
    }

    public function testParseReadsNothingFromAFilePhpItselfWouldRefuse(): void
    {
        $root = vfsStream::setup('project', null, ['Broken.php' => "<?php\nnamespace Bracketed { class InBrackets {} }\nclass Unbracketed {}\n"]);

        self::assertNull(SourceIndex::parse((new ParserFactory())->createForHostVersion(), $root->url().'/Broken.php'));
    }

    public function testParseReadsNothingFromAFileItCannotRead(): void
    {
        $root = vfsStream::setup('project');

        self::assertNull(SourceIndex::parse((new ParserFactory())->createForHostVersion(), $root->url().'/Missing.php'));
    }
}
