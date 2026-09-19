<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\ConstantNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConstantNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class ConstantNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Invoice::MAX_ITEMS', (new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Invoice::MAX_ITEMS', (new ConstantNodeId('', 'Invoice', 'MAX_ITEMS'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Invoice', $id->className);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new ConstantNodeId('App\Domain', 'Invoice', 'MAX_ITEMS'))->toString(),
            ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', ConstantNodeId::of('Invoice', 'MAX_ITEMS')->namespace);
    }
}
