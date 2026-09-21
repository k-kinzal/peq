<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\UnaryOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UnaryOperator::class)]
#[Small]
final class UnaryOperatorTest extends TestCase
{
    public function testTheOperatorsWrittenBeforeAValueAreTheOnlyOnesThereAre(): void
    {
        self::assertSame(
            [
                UnaryOperator::Not,
                UnaryOperator::Negate,
                UnaryOperator::Identity,
                UnaryOperator::IsNull,
                UnaryOperator::IsNotNull,
                UnaryOperator::IsTrue,
                UnaryOperator::IsNotTrue,
                UnaryOperator::IsFalse,
                UnaryOperator::IsNotFalse,
                UnaryOperator::IsUnknown,
                UnaryOperator::IsNotUnknown,
            ],
            UnaryOperator::cases(),
        );
    }

    #[DataProvider('providerOperatorsAndHowTheyAreWritten')]
    public function testSpellingWritesTheOperatorTheWayAQueryWritesIt(UnaryOperator $operator, string $written): void
    {
        self::assertSame($written, $operator->spelling());
    }

    /**
     * @return iterable<string, array{UnaryOperator, string}>
     */
    public static function providerOperatorsAndHowTheyAreWritten(): iterable
    {
        yield 'a negation' => [UnaryOperator::Not, 'NOT'];

        yield 'a reversed sign' => [UnaryOperator::Negate, '-'];

        yield 'a sign that changes nothing' => [UnaryOperator::Identity, '+'];

        yield 'a test for absence' => [UnaryOperator::IsNull, 'IS NULL'];

        yield 'a test for presence' => [UnaryOperator::IsNotNull, 'IS NOT NULL'];

        yield 'a test for truth' => [UnaryOperator::IsTrue, 'IS TRUE'];

        yield 'a test for anything but truth' => [UnaryOperator::IsNotTrue, 'IS NOT TRUE'];

        yield 'a test for falsehood' => [UnaryOperator::IsFalse, 'IS FALSE'];

        yield 'a test for anything but falsehood' => [UnaryOperator::IsNotFalse, 'IS NOT FALSE'];

        yield 'a test for the undecided' => [UnaryOperator::IsUnknown, 'IS UNKNOWN'];

        yield 'a test for anything decided' => [UnaryOperator::IsNotUnknown, 'IS NOT UNKNOWN'];
    }
}
