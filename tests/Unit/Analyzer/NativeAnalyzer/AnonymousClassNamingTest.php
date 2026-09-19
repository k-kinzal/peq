<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(AnonymousClassNaming::class)]
#[Small]
final class AnonymousClassNamingTest extends TestCase
{
    #[DataProvider('providerClassesOnOneLine')]
    public function testNameOfNamesAClassAfterWhereItStands(int $position, string $expected): void
    {
        $statements = ParsedSnippet::statements("<?php\n\$only = new class {};\n");
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);

        self::assertSame($expected, AnonymousClassNaming::of($statements)->nameOf($classes[$position], 'src/Only.php'));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function providerClassesOnOneLine(): iterable
    {
        yield 'the only class of its line carries no number' => [0, 'AnonymousClass'.md5('src/Only.php:2')];
    }

    public function testNameOfTellsTwoClassesOnOneLineApart(): void
    {
        $statements = ParsedSnippet::statements("<?php\n\$first = new class {}; \$second = new class {};\n");
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);
        $naming = AnonymousClassNaming::of($statements);

        self::assertNotSame($naming->nameOf($classes[0], 'src/Two.php'), $naming->nameOf($classes[1], 'src/Two.php'));
    }

    public function testOfNumbersTwoClassesOnOneLineInTheOrderTheyAreWritten(): void
    {
        $statements = ParsedSnippet::statements("<?php\n\$first = new class {}; \$second = new class {};\n");
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);

        self::assertSame(
            ['AnonymousClass'.md5('src/Two.php:2:1'), 'AnonymousClass'.md5('src/Two.php:2:2')],
            [AnonymousClassNaming::of($statements)->nameOf($classes[0], 'src/Two.php'), AnonymousClassNaming::of($statements)->nameOf($classes[1], 'src/Two.php')],
        );
    }

    public function testNameOfNamesTheSameClassDifferentlyInDifferentPlaces(): void
    {
        $statements = ParsedSnippet::statements("<?php\n\$only = new class {};\n");
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);
        $naming = AnonymousClassNaming::of($statements);

        self::assertNotSame($naming->nameOf($classes[0], 'src/Only.php'), $naming->nameOf($classes[0], 'lib/Only.php'));
    }

    public function testIsAnonymousRecognisesAClassWithNoName(): void
    {
        self::assertTrue(AnonymousClassNaming::isAnonymous(ParsedSnippet::classLike("<?php\n\$only = new class {};\n")));
    }

    public function testIsAnonymousDoesNotTakeANamedClassForAnAnonymousOne(): void
    {
        self::assertFalse(AnonymousClassNaming::isAnonymous(ParsedSnippet::classLike("<?php\nclass Named {}\n")));
    }
}
