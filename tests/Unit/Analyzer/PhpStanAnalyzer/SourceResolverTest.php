<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SourceResolverTest extends TestCase
{
    #[DataProvider('provideBuiltinNames')]
    #[Test]
    public function testBuiltinNamesReturnTrue(string $name): void
    {
        self::assertTrue(SourceResolver::isBuiltin($name), sprintf('Expected "%s" to be built-in', $name));
    }

    public static function provideBuiltinNames(): \Generator
    {
        $names = ['self', 'static', 'parent', 'int', 'string', 'float', 'bool', 'array', 'iterable', 'callable', 'void', 'object', 'mixed', 'null', 'false', 'true', 'never'];
        foreach ($names as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('provideCaseInsensitiveBuiltins')]
    #[Test]
    public function testBuiltinNamesAreCaseInsensitive(string $name): void
    {
        self::assertTrue(SourceResolver::isBuiltin($name), sprintf('Expected "%s" to be built-in (case-insensitive)', $name));
    }

    public static function provideCaseInsensitiveBuiltins(): \Generator
    {
        yield 'Int' => ['Int'];

        yield 'STRING' => ['STRING'];

        yield 'Self' => ['Self'];

        yield 'MIXED' => ['MIXED'];

        yield 'Bool' => ['Bool'];

        yield 'Never' => ['Never'];
    }

    #[DataProvider('provideNonBuiltinClassNames')]
    #[Test]
    public function testNonBuiltinClassNamesReturnFalse(string $name): void
    {
        self::assertFalse(SourceResolver::isBuiltin($name), sprintf('Expected "%s" to NOT be built-in', $name));
    }

    public static function provideNonBuiltinClassNames(): \Generator
    {
        yield 'MyClass' => ['MyClass'];

        yield 'stdClass' => ['stdClass'];

        yield 'Exception' => ['Exception'];

        yield 'DateTime' => ['DateTime'];

        yield 'Stringable' => ['Stringable'];

        yield 'App\Foo' => ['App\Foo'];
    }

    #[DataProvider('provideNonBuiltinFunctionNames')]
    #[Test]
    public function testNonBuiltinFunctionNamesReturnFalse(string $name): void
    {
        // isBuiltin() filters TYPE names, not function names.
        // Built-in functions like array_map are correctly not filtered.
        self::assertFalse(SourceResolver::isBuiltin($name), sprintf('Expected "%s" to NOT be built-in', $name));
    }

    public static function provideNonBuiltinFunctionNames(): \Generator
    {
        yield 'array_map' => ['array_map'];

        yield 'strlen' => ['strlen'];

        yield 'var_dump' => ['var_dump'];

        yield 'is_string' => ['is_string'];
    }

    #[DataProvider('provideEdgeCases')]
    #[Test]
    public function testEdgeCases(string $name): void
    {
        self::assertFalse(SourceResolver::isBuiltin($name), sprintf('Expected "%s" to NOT be built-in', $name));
    }

    public static function provideEdgeCases(): \Generator
    {
        yield 'empty string' => [''];

        yield 'integer (not int)' => ['integer'];

        yield 'boolean (not bool)' => ['boolean'];

        yield 'double (not float)' => ['double'];
    }

    #[Test]
    public function testGetNamespaceExtractsNamespace(): void
    {
        self::assertSame('App\Service', SourceResolver::getNamespace('App\Service\MyClass'));
        self::assertSame('', SourceResolver::getNamespace('MyClass'));
        self::assertSame('App', SourceResolver::getNamespace('App\Foo'));
    }

    #[Test]
    public function testGetShortNameExtractsClassName(): void
    {
        self::assertSame('MyClass', SourceResolver::getShortName('App\Service\MyClass'));
        self::assertSame('MyClass', SourceResolver::getShortName('MyClass'));
        self::assertSame('Foo', SourceResolver::getShortName('App\Foo'));
    }
}
