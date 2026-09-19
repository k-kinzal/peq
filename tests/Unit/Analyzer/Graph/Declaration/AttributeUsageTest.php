<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Declaration;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttributeUsage::class)]
#[Small]
final class AttributeUsageTest extends TestCase
{
    public function testAnAttributeKeepsTheNameItResolvesTo(): void
    {
        self::assertSame('App\Http\Route', (new AttributeUsage('App\Http\Route'))->name);
    }

    public function testAnAttributeKeepsItsArgumentsAsTheyWereWritten(): void
    {
        self::assertSame(["'/users'"], (new AttributeUsage('App\Http\Route', ["'/users'"]))->arguments);
    }

    public function testAnAttributeWrittenWithoutArgumentsHasNone(): void
    {
        self::assertSame([], (new AttributeUsage('Override'))->arguments);
    }

    #[DataProvider('providerAttributesAsTheyAreWritten')]
    public function testToStringWritesTheAttributeTheWayItsDeclarationWritesIt(AttributeUsage $usage, string $written): void
    {
        self::assertSame($written, $usage->toString());
    }

    /**
     * @return iterable<string, array{AttributeUsage, string}>
     */
    public static function providerAttributesAsTheyAreWritten(): iterable
    {
        yield 'without arguments' => [new AttributeUsage('Override'), 'Override'];

        yield 'with one argument' => [new AttributeUsage('Route', ["'/users'"]), "Route('/users')"];

        yield 'with several' => [new AttributeUsage('Route', ["'/users'", "methods: ['GET']"]), "Route('/users', methods: ['GET'])"];
    }
}
