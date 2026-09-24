<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Analyzer\Graph\Resolution\TypeConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TypeConstraint::class)]
#[Small]
final class TypeConstraintTest extends TestCase
{
    public function testOfKeepsIntersectionRequirements(): void
    {
        $type = TypeConstraint::of('(Port&Tagged)|Other|null');

        self::assertSame([['Port', 'Tagged'], ['Other']], $type->alternatives);
        self::assertSame(['Port', 'Tagged', 'Other'], $type->names());
    }

    public function testNamesExcludeScalarsThatAreNotReceiverClasses(): void
    {
        self::assertSame([], TypeConstraint::of('int|string|null')->names());
        self::assertSame([['Port']], TypeConstraint::of('?Port')->alternatives);
    }

    public function testAcceptsRequiresEveryIntersectionMember(): void
    {
        $hierarchy = new ClassHierarchy(new Graph());

        self::assertTrue(TypeConstraint::of('Port|Other')->accepts('Port', $hierarchy));
        self::assertFalse(TypeConstraint::of('Port&Tagged')->accepts('Port', $hierarchy));
    }
}
