<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Analyzer\Graph\Resolution\TypeConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(Graph::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(ClassHierarchy::class)]
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

    public function testNamesNormalizeWhitespaceAndQualifiedNamesWithoutDuplicateAlternatives(): void
    {
        self::assertSame(['Port', 'Tagged', 'Other'], TypeConstraint::of('(\Port & \Tagged)|\Port|Other')->names());
        self::assertFalse(TypeConstraint::of('Port')->accepts('Other', new ClassHierarchy(new Graph())));
    }
}
