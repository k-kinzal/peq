<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\ClosureNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClosureNodeId::class)]
#[UsesNamespace('App')]
#[Small]
final class ClosureNodeIdTest extends TestCase
{
    public function testToStringDistinguishesClosuresOnTheSameLineAndTraitImports(): void
    {
        self::assertSame('A{closure@2:3}', (new ClosureNodeId('A', 2, 3))->toString());
        self::assertSame('A{closure@2:4}', (new ClosureNodeId('A', 2, 4))->toString());
        self::assertSame('B{closure@2:3}', (new ClosureNodeId('B', 2, 3))->toString());
    }
}
