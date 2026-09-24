<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer;

use App\Analyzer\ExperimentAnalyzer\AnonymousClassNaming;
use App\Analyzer\ExperimentAnalyzer\AutoloadIndex;
use App\Analyzer\ExperimentAnalyzer\ClassLikeDeclaration;
use App\Analyzer\ExperimentAnalyzer\ParsedSource;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\SourceParser;
use org\bovigo\vfs\vfsStream;
use PhpParser\Node\Identifier;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ParsedSource::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(AutoloadIndex::class)]
#[UsesClass(ClassLikeDeclaration::class)]
#[UsesClass(SourceIndex::class)]
#[UsesClass(SourceParser::class)]
#[Small]
final class ParsedSourceTest extends TestCase
{
    public function testDeclarationOfFindsWhatTheFileDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertSame('App\Invoice', $source->declarationOf('App\Invoice')?->namespacedName?->toString());
    }

    public function testDeclarationOfFindsNothingForASymbolTheFileDoesNotDeclare(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertNull($source->declarationOf('App\Money'));
    }

    public function testDeclarationOfFindsWhatIsDeclaredInsideAnotherStatement(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nif (true) { class Conditional {} }\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertSame('App\Conditional', $source->declarationOf('App\Conditional')?->namespacedName?->toString());
    }

    public function testMethodBodyReadsTheBodyAsItIsWritten(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$money = new Money(); } }\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertSame('$money = new \App\Money();', (new Standard())->prettyPrint($source->methodBody('App\Invoice', 'total') ?? []));
    }

    public function testMethodBodyReadsNothingForAMethodTheFileDoesNotWrite(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertNull($source->methodBody('App\Invoice', 'total'));
    }

    public function testMethodBodyReadsNothingForAMethodWithNoBody(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nabstract class Invoice { abstract public function total(): void; }\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertNull($source->methodBody('App\Invoice', 'total'));
    }

    public function testMethodBodyReadsNothingForAnInterface(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\ninterface Invoice { public function total(): void; }\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertNull($source->methodBody('App\Invoice', 'total'));
    }

    public function testMethodsOfReadsEveryMethodADeclarationWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice { public function total(): void {} public function tax(): void {} }\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertSame(['total', 'tax'], array_keys($source->methodsOf('App\Invoice')));
    }

    public function testMethodsOfReadsNothingFromADeclarationTheFileDoesNotWrite(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        self::assertSame([], $source->methodsOf('App\Money'));
    }

    public function testMethodsOfKeepsTheMethodWrittenFirstWhenTwoAnswerToOneName(): void
    {
        $root = vfsStream::setup('project', null, ['Invoice.php' => "<?php\nnamespace App;\nclass Invoice { public function total(): int { \$inner = new class { public function total(): string { return ''; } }; return 1; } }\n"]);
        $source = SourceIndex::of([$root->url().'/Invoice.php'], $root->url())->sources()[0];

        $returnType = $source->methodsOf('App\Invoice')['total']->returnType;

        self::assertInstanceOf(Identifier::class, $returnType);
        self::assertSame('int', $returnType->toString());
    }
}
