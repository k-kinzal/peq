<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Declaration;

use App\Analyzer\Graph\Declaration\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Visibility::class)]
#[Small]
final class VisibilityTest extends TestCase
{
    public function testCasesAreSpelledTheWayPhpWritesThem(): void
    {
        self::assertSame('public', Visibility::Public->value);
        self::assertSame('protected', Visibility::Protected->value);
        self::assertSame('private', Visibility::Private->value);
    }

    public function testTheLevelsAreTheOnlyThreePhpHas(): void
    {
        self::assertSame([Visibility::Public, Visibility::Protected, Visibility::Private], Visibility::cases());
    }

    public function testAWrittenKeywordResolvesToItsCase(): void
    {
        self::assertSame(Visibility::Protected, Visibility::from('protected'));
    }

    #[DataProvider('providerWordsThatAreNotVisibilities')]
    public function testAWordPhpDoesNotWriteResolvesToNothing(string $written): void
    {
        self::assertNull(Visibility::tryFrom($written));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerWordsThatAreNotVisibilities(): iterable
    {
        yield 'internal' => ['internal'];

        yield 'nothing written' => [''];

        yield 'PUBLIC' => ['PUBLIC'];

        yield 'package-private' => ['package-private'];
    }
}
