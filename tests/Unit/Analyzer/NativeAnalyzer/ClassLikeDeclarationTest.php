<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

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
        $statements = (new ParserFactory())->createForHostVersion()->parse($code) ?? [];
        $declaration = (new NodeFinder())->findFirstInstanceOf($statements, ClassLike::class);
        self::assertNotNull($declaration);

        self::assertSame($expected, ClassLikeDeclaration::kindOf($declaration));
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
        $statements = array_values((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Invoice {}\n") ?? []);
        $node = (new NodeFinder())->findFirstInstanceOf($statements, ClassLike::class);
        self::assertNotNull($node);
        $source = new ParsedSource('/project/Invoice.php', $statements, AnonymousClassNaming::of($statements));

        $declaration = new ClassLikeDeclaration('App\Invoice', NodeKind::Klass, $source, $node);

        self::assertSame(['App\Invoice', NodeKind::Klass, $source, $node], [$declaration->name, $declaration->kind, $declaration->source, $declaration->node]);
    }
}
