<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Declaration;

use App\Analyzer\Graph\Declaration\Modifiers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Modifiers::class)]
#[Small]
final class ModifiersTest extends TestCase
{
    public function testADeclarationWithNoKeywordCarriesNone(): void
    {
        $modifiers = new Modifiers();

        self::assertFalse($modifiers->static);
        self::assertFalse($modifiers->abstract);
        self::assertFalse($modifiers->final);
        self::assertFalse($modifiers->readonly);
    }

    public function testEachKeywordIsRecordedOnItsOwn(): void
    {
        $modifiers = new Modifiers(static: true, abstract: false, final: true, readonly: false);

        self::assertTrue($modifiers->static);
        self::assertFalse($modifiers->abstract);
        self::assertTrue($modifiers->final);
        self::assertFalse($modifiers->readonly);
    }

    public function testNoneReportsADeclarationWithNothingWrittenOnIt(): void
    {
        self::assertTrue((new Modifiers())->none());
    }

    #[DataProvider('providerOneKeywordAtATime')]
    public function testNoneReportsADeclarationCarryingAnyKeyword(Modifiers $modifiers): void
    {
        self::assertFalse($modifiers->none());
    }

    /**
     * @return iterable<string, array{Modifiers}>
     */
    public static function providerOneKeywordAtATime(): iterable
    {
        yield 'static' => [new Modifiers(static: true)];

        yield 'abstract' => [new Modifiers(abstract: true)];

        yield 'final' => [new Modifiers(final: true)];

        yield 'readonly' => [new Modifiers(readonly: true)];
    }
}
