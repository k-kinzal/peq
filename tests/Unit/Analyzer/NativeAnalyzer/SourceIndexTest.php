<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use App\Analyzer\SourceParser;
use org\bovigo\vfs\vfsStream;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use WeakReference;

/**
 * @internal
 */
#[CoversClass(SourceIndex::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(AutoloadIndex::class)]
#[UsesClass(ClassLikeDeclaration::class)]
#[UsesClass(ParsedSource::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(\App\Analyzer\CachedSyntax::class)]
#[UsesClass(\App\Analyzer\Declaration\Calls\WrittenCalls::class)]
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

    public function testIterateSourcesYieldsFilesInSelectionOrder(): void
    {
        $root = vfsStream::setup('project', null, ['First.php' => '<?php class One {}', 'Second.php' => '<?php class Two {}']);
        $index = SourceIndex::of([$root->url().'/First.php', $root->url().'/Second.php'], $root->url());

        self::assertSame(
            ['vfs://project/First.php', 'vfs://project/Second.php'],
            array_map(static fn (ParsedSource $source): string => $source->path, iterator_to_array($index->iterateSources())),
        );
    }

    public function testSourceOfReleasesPreviousSyntaxAndReloadsCrossFileDeclarations(): void
    {
        $root = vfsStream::setup('project', null, ['First.php' => '<?php trait Shared {} function helper() {}', 'Second.php' => '<?php class Consumer { use Shared; }']);
        $index = SourceIndex::of([$root->url().'/First.php', $root->url().'/Second.php'], $root->url());
        $first = $index->sourceOf($root->url().'/First.php');
        self::assertNotNull($first);
        $reference = WeakReference::create($first);
        self::assertSame($first, $index->sourceOf($root->url().'/First.php'));
        unset($first);

        self::assertNotNull($index->sourceOf($root->url().'/Second.php'));
        self::assertNull($reference->get());
        self::assertTrue($index->declaresFunction('helper'));
        self::assertTrue($index->knowsClass('Shared'));
        self::assertSame(NodeKind::Trait, $index->classLike('Shared')?->kind);
        self::assertSame('vfs://project/First.php', $index->classLike('Shared')->source->path);
    }

    public function testSourceOfReusesActiveSyntaxAcrossTraitLookups(): void
    {
        $root = vfsStream::setup('project', null, [
            'Consumer.php' => '<?php class Consumer { use Shared; }',
            'Shared.php' => '<?php trait Shared { use Nested; }',
            'Nested.php' => '<?php trait Nested { function run() {} }',
        ]);
        $index = SourceIndex::of([$root->url().'/Consumer.php', $root->url().'/Shared.php', $root->url().'/Nested.php'], $root->url());
        $consumer = $index->sourceOf($root->url().'/Consumer.php');
        $shared = $index->classLike('Shared');
        $nested = $index->classLike('Nested');

        self::assertNotNull($consumer);
        self::assertNotNull($shared);
        self::assertNotNull($nested);
        self::assertSame($consumer, $index->sourceOf($root->url().'/Consumer.php'));
        self::assertSame($shared->source, $index->classLike('Shared')?->source);
        self::assertSame($nested->source, $index->classLike('Nested')?->source);
        $reference = WeakReference::create($shared->source);
        unset($shared);

        self::assertNull($reference->get());
        self::assertSame(NodeKind::Trait, $index->classLike('Shared')->kind);
    }

    public function testSourceOfReportsAFileRemovedAfterIndexing(): void
    {
        $root = vfsStream::setup('project', null, ['Removed.php' => '<?php class Removed {}']);
        $index = SourceIndex::of([$root->url().'/Removed.php'], $root->url());
        unlink($root->url().'/Removed.php');

        self::assertNull($index->sourceOf($root->url().'/Removed.php'));
    }
}
