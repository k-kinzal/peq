<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use PhpParser\Node\Identifier;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(ParsedSource::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[Small]
final class ParsedSourceTest extends TestCase
{
    public function testDeclarationOfFindsWhatTheFileDeclares(): void
    {
        self::assertNotNull(ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice {}\n")->declarationOf('App\Invoice'));
    }

    public function testDeclarationOfFindsNothingForASymbolTheFileDoesNotDeclare(): void
    {
        self::assertNull(ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice {}\n")->declarationOf('App\Money'));
    }

    public function testDeclarationOfFindsWhatIsDeclaredInsideAnotherStatement(): void
    {
        self::assertNotNull(ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nif (true) { class Conditional {} }\n")->declarationOf('App\Conditional'));
    }

    public function testMethodBodyReadsTheBodyAsItIsWritten(): void
    {
        $body = ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$money = new Money(); } }\n")->methodBody('App\Invoice', 'total');

        self::assertSame('$money = new \App\Money();', (new Standard())->prettyPrint($body ?? []));
    }

    public function testMethodBodyReadsNothingForAMethodTheFileDoesNotWrite(): void
    {
        self::assertNull(ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice {}\n")->methodBody('App\Invoice', 'total'));
    }

    public function testMethodBodyReadsNothingForAMethodWithNoBody(): void
    {
        self::assertNull(ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nabstract class Invoice { abstract public function total(): void; }\n")->methodBody('App\Invoice', 'total'));
    }

    public function testMethodBodyReadsNothingForAnInterface(): void
    {
        self::assertNull(ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\ninterface Invoice { public function total(): void; }\n")->methodBody('App\Invoice', 'total'));
    }

    public function testMethodsOfReadsEveryMethodADeclarationWrites(): void
    {
        $methods = ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice { public function total(): void {} public function tax(): void {} }\n")->methodsOf('App\Invoice');

        self::assertSame(['total', 'tax'], array_keys($methods));
    }

    public function testMethodsOfReadsNothingFromADeclarationTheFileDoesNotWrite(): void
    {
        self::assertSame([], ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice {}\n")->methodsOf('App\Money'));
    }

    public function testMethodsOfKeepsTheMethodWrittenFirstWhenTwoAnswerToOneName(): void
    {
        $methods = ParsedSnippet::source('/project/Invoice.php', "<?php\nnamespace App;\nclass Invoice { public function total(): int { \$inner = new class { public function total(): string { return ''; } }; return 1; } }\n")->methodsOf('App\Invoice');
        $returnType = $methods['total']->returnType;
        self::assertInstanceOf(Identifier::class, $returnType);

        self::assertSame('int', $returnType->toString());
    }
}
