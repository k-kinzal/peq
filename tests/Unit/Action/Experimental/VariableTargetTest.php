<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use App\Action\Experimental\InspectionRejected;
use App\Action\Experimental\VariableTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariableTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InspectionRejected::class)]
final class VariableTargetTest extends TestCase
{
    #[DataProvider('providerAddresses')]
    public function testParseAcceptsEmbeddedAndLegacyVariables(string $address, ?string $option, string $symbol, ?string $variable): void
    {
        $target = VariableTarget::parse($address, $option);
        self::assertSame($symbol, $target->symbol);
        self::assertSame($variable, $target->variable);
    }

    /**
     * @return iterable<array{string, ?string, string, ?string}>
     */
    public static function providerAddresses(): iterable
    {
        yield ['SqlCatalog\Analyzer:analyzePaths$sources', null, 'SqlCatalog\Analyzer::analyzePaths', '$sources'];

        yield ['SqlCatalog\Analyzer:$sources', null, 'SqlCatalog\Analyzer::$sources', '$sources'];

        yield ['C::f$x', 'x', 'C::f', '$x'];

        yield ['C::f', 'x', 'C::f', 'x'];

        yield ['C:f', null, 'C::f', null];

        yield ['ns\f$x', null, 'ns\f', '$x'];

        yield ['C::$x', '$x', 'C::$x', '$x'];
    }

    #[DataProvider('providerInvalidAddresses')]
    public function testParseRejectsMalformedOrConflictingVariables(string $address, ?string $option): void
    {
        $this->expectException(InspectionRejected::class);
        VariableTarget::parse($address, $option);
    }

    /**
     * @return iterable<array{string, ?string}>
     */
    public static function providerInvalidAddresses(): iterable
    {
        yield ['C:f$x$y', null];

        yield ['C:f$', null];

        yield ['C:f$1x', null];

        yield ['C:f$x', 'y'];

        yield ['$x', null];

        yield ['C:f:g$x', null];
    }
}
