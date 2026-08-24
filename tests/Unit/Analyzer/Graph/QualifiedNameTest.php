<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\QualifiedName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QualifiedName::class)]
#[Small]
final class QualifiedNameTest extends TestCase
{
    public function testANamespacedNameSplitsAtItsLastSeparator(): void
    {
        $name = new QualifiedName('App\Domain\Billing\Invoice');

        self::assertSame('App\Domain\Billing', $name->namespace);
        self::assertSame('Invoice', $name->shortName);
    }

    public function testAGlobalNameHasNoNamespace(): void
    {
        $name = new QualifiedName('Invoice');

        self::assertSame('', $name->namespace);
        self::assertSame('Invoice', $name->shortName);
    }

    public function testTheWrittenNameIsKeptAsItWasGiven(): void
    {
        self::assertSame('App\Domain\Invoice', (new QualifiedName('App\Domain\Invoice'))->fullName);
    }

    #[DataProvider('providerBuiltinNames')]
    public function testIsBuiltinTypeRecognisesWhatPhpResolvesItself(string $written): void
    {
        self::assertTrue((new QualifiedName($written))->isBuiltinType());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBuiltinNames(): iterable
    {
        yield 'scalar' => ['int'];

        yield 'compound' => ['iterable'];

        yield 'return-only' => ['never'];

        yield 'relative class keyword' => ['parent'];

        yield 'written in any case' => ['STRING'];
    }

    #[DataProvider('providerDeclaredNames')]
    public function testIsBuiltinTypeRejectsWhatACodebaseDeclares(string $written): void
    {
        self::assertFalse((new QualifiedName($written))->isBuiltinType());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeclaredNames(): iterable
    {
        yield 'namespaced class' => ['App\Domain\Invoice'];

        yield 'global class' => ['Invoice'];

        yield 'a name merely containing a builtin' => ['App\Integer'];
    }

    #[DataProvider('providerAdmissibleIdentifiers')]
    public function testIsIdentifierAdmitsEveryNamePhpAllows(string $written): void
    {
        self::assertTrue(QualifiedName::isIdentifier($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerAdmissibleIdentifiers(): iterable
    {
        yield 'a class name' => ['Invoice'];

        yield 'a method name' => ['totalAmount'];

        yield 'a constant name' => ['MAX_ITEMS'];

        yield 'a leading underscore' => ['_internal'];

        yield 'digits after the first character' => ['Base64Encoder'];

        yield 'a multibyte name' => ['Rechnungsprüfung'];
    }

    #[DataProvider('providerInadmissibleIdentifiers')]
    public function testIsIdentifierRejectsWhatPhpWouldNotAccept(string $written): void
    {
        self::assertFalse(QualifiedName::isIdentifier($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInadmissibleIdentifiers(): iterable
    {
        yield 'nothing at all' => [''];

        yield 'a qualified name' => ['App\Domain\Invoice'];

        yield 'a leading digit' => ['1st'];

        yield 'a space' => ['total amount'];

        yield 'a call rather than a name' => ['total()'];
    }

    #[DataProvider('providerAdmissibleNamespaces')]
    public function testIsNamespaceAdmitsEveryNamespacePhpAllows(string $written): void
    {
        self::assertTrue(QualifiedName::isNamespace($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerAdmissibleNamespaces(): iterable
    {
        yield 'a single segment' => ['App'];

        yield 'several segments' => ['App\Domain\Billing'];

        yield 'a leading underscore' => ['_Private\Billing'];

        yield 'digits after the first character' => ['App2\Domain'];
    }

    #[DataProvider('providerInadmissibleNamespaces')]
    public function testIsNamespaceRejectsWhatPhpWouldNotAccept(string $written): void
    {
        self::assertFalse(QualifiedName::isNamespace($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInadmissibleNamespaces(): iterable
    {
        yield 'the global namespace, which is written as no namespace' => [''];

        yield 'a leading separator' => ['\App\Domain'];

        yield 'a trailing separator' => ['App\Domain\\'];

        yield 'an empty segment' => ['App\\\Domain'];

        yield 'a leading digit' => ['1App\Domain'];
    }
}
