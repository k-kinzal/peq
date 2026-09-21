<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Pattern;

use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Quantifier::class)]
#[Small]
final class QuantifierTest extends TestCase
{
    public function testARepetitionCarriesItsBounds(): void
    {
        $quantifier = new Quantifier(1, 3);

        self::assertSame(1, $quantifier->least);
        self::assertSame(3, $quantifier->most);
    }

    public function testARepetitionThatSaysNothingMayMatchNothingAndGoOnForever(): void
    {
        $quantifier = new Quantifier();

        self::assertSame(0, $quantifier->least);
        self::assertNull($quantifier->most);
    }

    public function testExactlyBoundsBothEndsTheSameWay(): void
    {
        $quantifier = Quantifier::exactly(3);

        self::assertSame(3, $quantifier->least);
        self::assertSame(3, $quantifier->most);
    }

    #[DataProvider('providerRepetitionsAndWhetherTheyAreAllowed')]
    public function testAllowsReportsWhetherThatManyRepetitionsMatch(Quantifier $quantifier, int $times, bool $allowed): void
    {
        self::assertSame($allowed, $quantifier->allows($times));
    }

    /**
     * @return iterable<string, array{Quantifier, int, bool}>
     */
    public static function providerRepetitionsAndWhetherTheyAreAllowed(): iterable
    {
        yield 'inside the bounds' => [new Quantifier(1, 3), 2, true];

        yield 'at the lower bound' => [new Quantifier(1, 3), 1, true];

        yield 'at the upper bound' => [new Quantifier(1, 3), 3, true];

        yield 'below the lower bound' => [new Quantifier(1, 3), 0, false];

        yield 'past the upper bound' => [new Quantifier(1, 3), 4, false];

        yield 'with no upper bound at all' => [new Quantifier(1), 99, true];

        yield 'with no repetition allowed at all' => [new Quantifier(0, 0), 0, true];
    }
}
