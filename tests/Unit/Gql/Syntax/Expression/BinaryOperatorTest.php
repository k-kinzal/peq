<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\BinaryOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BinaryOperator::class)]
#[Small]
final class BinaryOperatorTest extends TestCase
{
    public function testTheOperatorsWrittenBetweenTwoValuesAreTheOnlyOnesThereAre(): void
    {
        self::assertSame(
            [
                BinaryOperator::Add,
                BinaryOperator::Subtract,
                BinaryOperator::Multiply,
                BinaryOperator::Divide,
                BinaryOperator::Concatenate,
                BinaryOperator::Equal,
                BinaryOperator::NotEqual,
                BinaryOperator::Less,
                BinaryOperator::LessOrEqual,
                BinaryOperator::Greater,
                BinaryOperator::GreaterOrEqual,
                BinaryOperator::And,
                BinaryOperator::Xor,
                BinaryOperator::Or,
            ],
            BinaryOperator::cases(),
        );
    }

    #[DataProvider('providerOperatorsAndHowTheyAreWritten')]
    public function testSpellingWritesTheOperatorTheWayAQueryWritesIt(BinaryOperator $operator, string $written): void
    {
        self::assertSame($written, $operator->spelling());
    }

    /**
     * @return iterable<string, array{BinaryOperator, string}>
     */
    public static function providerOperatorsAndHowTheyAreWritten(): iterable
    {
        yield 'addition' => [BinaryOperator::Add, '+'];

        yield 'subtraction' => [BinaryOperator::Subtract, '-'];

        yield 'multiplication' => [BinaryOperator::Multiply, '*'];

        yield 'division' => [BinaryOperator::Divide, '/'];

        yield 'concatenation' => [BinaryOperator::Concatenate, '||'];

        yield 'equality' => [BinaryOperator::Equal, '='];

        yield 'inequality' => [BinaryOperator::NotEqual, '<>'];

        yield 'ordering' => [BinaryOperator::Less, '<'];

        yield 'ordering or equality' => [BinaryOperator::LessOrEqual, '<='];

        yield 'the other ordering' => [BinaryOperator::Greater, '>'];

        yield 'the other ordering or equality' => [BinaryOperator::GreaterOrEqual, '>='];

        yield 'conjunction' => [BinaryOperator::And, 'AND'];

        yield 'exclusive disjunction' => [BinaryOperator::Xor, 'XOR'];

        yield 'disjunction' => [BinaryOperator::Or, 'OR'];
    }
}
