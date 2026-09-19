<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Declaration;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SymbolDeclaration::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Signature::class)]
#[Small]
final class SymbolDeclarationTest extends TestCase
{
    public function testADeclarationKeepsEverythingTheSourceSaidAboutIt(): void
    {
        $signature = new Signature([], 'void');
        $route = new AttributeUsage('App\Http\Route');
        $declared = new SymbolDeclaration(
            visibility: Visibility::Protected,
            modifiers: new Modifiers(static: true),
            signature: $signature,
            attributes: [$route],
            type: 'int',
            value: '3',
            deprecated: true,
        );

        self::assertSame(Visibility::Protected, $declared->visibility);
        self::assertTrue($declared->modifiers->static);
        self::assertSame($signature, $declared->signature);
        self::assertSame([$route], $declared->attributes);
        self::assertSame('int', $declared->type);
        self::assertSame('3', $declared->value);
        self::assertTrue($declared->deprecated);
    }

    public function testADeclarationOfAKindThatCarriesNoVisibilityHasNone(): void
    {
        self::assertNull((new SymbolDeclaration())->visibility);
    }

    public function testAttributeNamesAreTheNamesTheAttributeClassesAreDeclaredUnder(): void
    {
        $declared = new SymbolDeclaration(attributes: [
            new AttributeUsage('App\Http\Route'),
            new AttributeUsage('App\Http\Middleware'),
        ]);

        self::assertSame(['App\Http\Route', 'App\Http\Middleware'], $declared->attributeNames());
    }

    public function testAttributeNamesOfADeclarationCarryingNoneAreNone(): void
    {
        self::assertSame([], (new SymbolDeclaration())->attributeNames());
    }

    #[DataProvider('providerNamesThatNameTheSameAttribute')]
    public function testHasAttributeFindsAnAttributeHoweverItsNameIsWritten(string $asked): void
    {
        $declared = new SymbolDeclaration(attributes: [new AttributeUsage('App\Http\Route')]);

        self::assertTrue($declared->hasAttribute($asked));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNamesThatNameTheSameAttribute(): iterable
    {
        yield 'as declared' => ['App\Http\Route'];

        yield 'in another case' => ['app\http\route'];

        yield 'written absolute' => ['\App\Http\Route'];
    }

    public function testHasAttributeDoesNotFindAnAttributeThatIsNotWritten(): void
    {
        $declared = new SymbolDeclaration(attributes: [new AttributeUsage('App\Http\Route')]);

        self::assertFalse($declared->hasAttribute('App\Http\Middleware'));
    }

    public function testEmptyReportsADeclarationThatSaysNothing(): void
    {
        self::assertTrue((new SymbolDeclaration())->empty());
    }

    #[DataProvider('providerDeclarationsThatSaySomething')]
    public function testEmptyReportsADeclarationThatSaysAnythingAtAll(SymbolDeclaration $declared): void
    {
        self::assertFalse($declared->empty());
    }

    /**
     * @return iterable<string, array{SymbolDeclaration}>
     */
    public static function providerDeclarationsThatSaySomething(): iterable
    {
        yield 'a visibility' => [new SymbolDeclaration(visibility: Visibility::Public)];

        yield 'a keyword' => [new SymbolDeclaration(modifiers: new Modifiers(final: true))];

        yield 'a signature' => [new SymbolDeclaration(signature: new Signature())];

        yield 'an attribute' => [new SymbolDeclaration(attributes: [new AttributeUsage('Override')])];

        yield 'a type' => [new SymbolDeclaration(type: 'int')];

        yield 'a value' => [new SymbolDeclaration(value: '3')];

        yield 'a deprecation' => [new SymbolDeclaration(deprecated: true)];
    }
}
