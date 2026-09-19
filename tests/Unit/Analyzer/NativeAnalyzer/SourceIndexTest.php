<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

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
        $index = ParsedSnippet::index(['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\ntrait Shared {}\n"]);

        self::assertSame(NodeKind::Trait, $index->classLike('App\Shared')?->kind);
    }

    public function testClassLikeFindsADeclarationWhateverCasingItIsAskedFor(): void
    {
        $index = ParsedSnippet::index(['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);

        self::assertSame('App\Invoice', $index->classLike('app\invoice')?->name);
    }

    public function testClassLikeFindsNothingForASymbolNoAnalysedFileWrites(): void
    {
        self::assertNull(ParsedSnippet::index(['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\n"])->classLike('App\Money'));
    }

    public function testOfKeepsTheDeclarationOfTheFileReadFirstWhenTwoNameTheSameSymbol(): void
    {
        $index = ParsedSnippet::index([
            'First.php' => "<?php\nnamespace App;\nclass Invoice {}\n",
            'Second.php' => "<?php\nnamespace App;\nclass Invoice {}\n",
        ]);

        self::assertStringEndsWith('First.php', $index->classLike('App\Invoice')?->source->path ?? '');
    }

    public function testDeclaresFunctionFindsTheFunctionsTheAnalysedFilesDeclare(): void
    {
        self::assertTrue(ParsedSnippet::index(['Declared.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"])->declaresFunction('App\helper'));
    }

    public function testDeclaresFunctionFindsNoFunctionNoAnalysedFileDeclares(): void
    {
        self::assertFalse(ParsedSnippet::index(['Declared.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"])->declaresFunction('App\missing'));
    }

    public function testSourcesAreReadInTheOrderTheyAreGiven(): void
    {
        $index = ParsedSnippet::index(['First.php' => "<?php\nclass One {}\n", 'Second.php' => "<?php\nclass Two {}\n"]);

        self::assertSame(['First.php', 'Second.php'], array_map(static fn (ParsedSource $source): string => basename($source->path), $index->sources()));
    }

    public function testSourceOfFindsAnAnalysedFileByItsPath(): void
    {
        $index = ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]);
        $path = $index->sources()[0]->path;

        self::assertSame($path, $index->sourceOf($path)?->path);
    }

    public function testSourceOfFindsNothingForAPathTheAnalysisWasNotGiven(): void
    {
        self::assertNull(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"])->sourceOf('/nowhere/Only.php'));
    }

    public function testOfLeavesOutAFilePhpItselfWouldRefuse(): void
    {
        $index = ParsedSnippet::index([
            'Broken.php' => "<?php\nnamespace Bracketed { class InBrackets {} }\nclass Unbracketed {}\n",
            'Intact.php' => "<?php\nnamespace App;\nclass Survivor {}\n",
        ]);

        self::assertSame(['Intact.php'], array_map(static fn (ParsedSource $source): string => basename($source->path), $index->sources()));
    }

    public function testOfStillReadsTheFilesAroundOnePhpWouldRefuse(): void
    {
        $index = ParsedSnippet::index([
            'Broken.php' => "<?php\nnamespace Bracketed { class InBrackets {} }\nclass Unbracketed {}\n",
            'Intact.php' => "<?php\nnamespace App;\nclass Survivor {}\n",
        ]);

        self::assertNotNull($index->classLike('App\Survivor'));
    }

    public function testOfLeavesOutAFileItCannotRead(): void
    {
        self::assertSame([], SourceIndex::of([sys_get_temp_dir().'/peq-no-such-file.php'], sys_get_temp_dir())->sources());
    }

    public function testKnowsClassFindsWhatAnAnalysedFileDeclares(): void
    {
        self::assertTrue(ParsedSnippet::index(['Declared.php' => "<?php\nnamespace App;\nclass Invoice {}\n"])->knowsClass('App\Invoice'));
    }

    public function testKnowsClassFindsWhatPhpItselfDeclares(): void
    {
        self::assertTrue(ParsedSnippet::index(['Declared.php' => "<?php\nclass One {}\n"])->knowsClass('RuntimeException'));
    }

    public function testKnowsClassFindsNothingWhereNothingDeclaresIt(): void
    {
        self::assertFalse(ParsedSnippet::index(['Declared.php' => "<?php\nclass One {}\n"])->knowsClass('Vendor\NotInstalled\Missing'));
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
        $index = ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]);

        self::assertSame('Only.php', $index->relativePathOf($index->sources()[0]->path));
    }

    public function testParseReadsTheStatementsOfAFile(): void
    {
        $index = ParsedSnippet::index(['Only.php' => "<?php\nnamespace App;\nclass One {}\n"]);

        self::assertCount(1, $index->sources()[0]->statements);
    }
}
