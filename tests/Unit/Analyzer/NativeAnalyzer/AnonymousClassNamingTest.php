<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

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
        $statements = array_values((new ParserFactory())->createForHostVersion()->parse("<?php\n\$only = new class {};\n") ?? []);
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
        $statements = array_values((new ParserFactory())->createForHostVersion()->parse("<?php\n\$first = new class {}; \$second = new class {};\n") ?? []);
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);
        $naming = AnonymousClassNaming::of($statements);

        self::assertNotSame($naming->nameOf($classes[0], 'src/Two.php'), $naming->nameOf($classes[1], 'src/Two.php'));
    }

    public function testOfNumbersTwoClassesOnOneLineInTheOrderTheyAreWritten(): void
    {
        $statements = array_values((new ParserFactory())->createForHostVersion()->parse("<?php\n\$first = new class {}; \$second = new class {};\n") ?? []);
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);
        $naming = AnonymousClassNaming::of($statements);

        self::assertSame(
            ['AnonymousClass'.md5('src/Two.php:2:1'), 'AnonymousClass'.md5('src/Two.php:2:2')],
            [$naming->nameOf($classes[0], 'src/Two.php'), $naming->nameOf($classes[1], 'src/Two.php')],
        );
    }

    public function testNameOfNamesTheSameClassDifferentlyInDifferentPlaces(): void
    {
        $statements = array_values((new ParserFactory())->createForHostVersion()->parse("<?php\n\$only = new class {};\n") ?? []);
        $classes = (new NodeFinder())->findInstanceOf($statements, Class_::class);
        $naming = AnonymousClassNaming::of($statements);

        self::assertNotSame($naming->nameOf($classes[0], 'src/Only.php'), $naming->nameOf($classes[0], 'lib/Only.php'));
    }

    public function testIsAnonymousRecognisesAClassWithNoName(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse("<?php\n\$only = new class {};\n") ?? [];
        $class = (new NodeFinder())->findFirstInstanceOf($statements, Class_::class);
        self::assertNotNull($class);

        self::assertTrue(AnonymousClassNaming::isAnonymous($class));
    }

    public function testIsAnonymousDoesNotTakeANamedClassForAnAnonymousOne(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse("<?php\nclass Named {}\n") ?? [];
        $class = (new NodeFinder())->findFirstInstanceOf($statements, Class_::class);
        self::assertNotNull($class);

        self::assertFalse(AnonymousClassNaming::isAnonymous($class));
    }
}
