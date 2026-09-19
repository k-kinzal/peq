<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Syntax\Expression;

use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[Small]
final class PropertyExpressionTest extends TestCase
{
    public function testAPropertyCarriesWhatItIsReadOffAndWhatItIsCalled(): void
    {
        $subject = new VariableExpression('p');
        $read = new PropertyExpression($subject, 'firstName');

        self::assertSame($subject, $read->subject);
        self::assertSame('firstName', $read->property);
    }
}
