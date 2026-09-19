<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(ClassLikeDeclaration::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(ParsedSource::class)]
#[Small]
final class ClassLikeDeclarationTest extends TestCase
{
    #[DataProvider('providerDeclarations')]
    public function testKindOfReadsTheKindOutOfTheSyntax(string $code, NodeKind $expected): void
    {
        self::assertSame($expected, ClassLikeDeclaration::kindOf(ParsedSnippet::classLike($code)));
    }

    /**
     * @return iterable<string, array{string, NodeKind}>
     */
    public static function providerDeclarations(): iterable
    {
        yield 'a class' => ["<?php\nclass Written {}\n", NodeKind::Klass];

        yield 'an interface' => ["<?php\ninterface Written {}\n", NodeKind::Interface];

        yield 'a trait' => ["<?php\ntrait Written {}\n", NodeKind::Trait];

        yield 'an enum' => ["<?php\nenum Written {}\n", NodeKind::Enum];

        yield 'an anonymous class is still a class' => ["<?php\n\$written = new class {};\n", NodeKind::Klass];
    }

    public function testRemembersWhatItWasIndexedAs(): void
    {
        $statements = ParsedSnippet::statements("<?php\nnamespace App;\nclass Invoice {}\n");
        $source = new ParsedSource('/project/Invoice.php', $statements, AnonymousClassNaming::of($statements));
        $declaration = new ClassLikeDeclaration('App\Invoice', NodeKind::Klass, $source, ParsedSnippet::classLike("<?php\nnamespace App;\nclass Invoice {}\n"));

        self::assertSame(['App\Invoice', NodeKind::Klass, '/project/Invoice.php'], [$declaration->name, $declaration->kind, $declaration->source->path]);
    }
}
