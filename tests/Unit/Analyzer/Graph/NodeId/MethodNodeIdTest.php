<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class MethodNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Invoice::total', (new MethodNodeId('App\Domain', 'Invoice', 'total'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Invoice::total', (new MethodNodeId('', 'Invoice', 'total'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = MethodNodeId::of('App\Domain\Invoice', 'total');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Invoice', $id->className);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new MethodNodeId('App\Domain', 'Invoice', 'total'))->toString(),
            MethodNodeId::of('App\Domain\Invoice', 'total')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', MethodNodeId::of('Invoice', 'total')->namespace);
    }
}
