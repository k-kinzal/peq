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
    #[DataProvider('providerOperatorsAndHowTightlyTheyBind')]
    public function testBindingRanksTheOperatorTheWayGqlRanksIt(BinaryOperator $tighter, BinaryOperator $looser): void
    {
        self::assertLessThan($looser->binding(), $tighter->binding());
    }

    /**
     * @return iterable<string, array{BinaryOperator, BinaryOperator}>
     */
    public static function providerOperatorsAndHowTightlyTheyBind(): iterable
    {
        yield 'multiplication before addition' => [BinaryOperator::Multiply, BinaryOperator::Add];

        yield 'addition before comparison' => [BinaryOperator::Add, BinaryOperator::Equal];

        yield 'comparison before conjunction' => [BinaryOperator::Equal, BinaryOperator::And];

        yield 'conjunction before exclusive disjunction' => [BinaryOperator::And, BinaryOperator::Xor];

        yield 'exclusive disjunction before disjunction' => [BinaryOperator::Xor, BinaryOperator::Or];
    }

    public function testBindingPutsConcatenationWithAdditionAsSqlDoes(): void
    {
        self::assertSame(BinaryOperator::Add->binding(), BinaryOperator::Concatenate->binding());
    }

    public function testBindingPutsEveryComparisonAtTheSameStrength(): void
    {
        self::assertSame(BinaryOperator::Equal->binding(), BinaryOperator::StartsWith->binding());
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

        yield 'membership' => [BinaryOperator::In, 'IN'];

        yield 'refused membership' => [BinaryOperator::NotIn, 'NOT IN'];

        yield 'a substring' => [BinaryOperator::Contains, 'CONTAINS'];

        yield 'a prefix' => [BinaryOperator::StartsWith, 'STARTS WITH'];

        yield 'a suffix' => [BinaryOperator::EndsWith, 'ENDS WITH'];

        yield 'conjunction' => [BinaryOperator::And, 'AND'];

        yield 'exclusive disjunction' => [BinaryOperator::Xor, 'XOR'];

        yield 'disjunction' => [BinaryOperator::Or, 'OR'];
    }

    public function testEveryOperatorIsWrittenAsSomething(): void
    {
        $written = array_map(static fn (BinaryOperator $operator): string => $operator->spelling(), BinaryOperator::cases());

        self::assertNotContains('', $written);
    }
}
