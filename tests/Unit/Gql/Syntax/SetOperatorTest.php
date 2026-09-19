<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax;

use App\Gql\Syntax\SetOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SetOperator::class)]
#[Small]
final class SetOperatorTest extends TestCase
{
    public function testTheOperatorsAreTheOnlyWaysTwoResultsAreCombined(): void
    {
        self::assertSame(
            [
                SetOperator::UnionAll,
                SetOperator::Union,
                SetOperator::Except,
                SetOperator::Intersect,
                SetOperator::Otherwise,
            ],
            SetOperator::cases(),
        );
    }

    #[DataProvider('providerOperatorsAndHowTheyAreWritten')]
    public function testSpellingWritesTheOperatorTheWayAQueryWritesIt(SetOperator $operator, string $written): void
    {
        self::assertSame($written, $operator->spelling());
    }

    /**
     * @return iterable<string, array{SetOperator, string}>
     */
    public static function providerOperatorsAndHowTheyAreWritten(): iterable
    {
        yield 'keeping every row' => [SetOperator::UnionAll, 'UNION ALL'];

        yield 'dropping the rows that repeat' => [SetOperator::Union, 'UNION'];

        yield 'taking the difference' => [SetOperator::Except, 'EXCEPT'];

        yield 'taking what both have' => [SetOperator::Intersect, 'INTERSECT'];

        yield 'falling back to the other side' => [SetOperator::Otherwise, 'OTHERWISE'];
    }
}
