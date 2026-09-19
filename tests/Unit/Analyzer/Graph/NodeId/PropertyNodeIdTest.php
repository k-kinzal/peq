<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class PropertyNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Invoice::lines', (new PropertyNodeId('App\Domain', 'Invoice', 'lines'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Invoice::lines', (new PropertyNodeId('', 'Invoice', 'lines'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = PropertyNodeId::of('App\Domain\Invoice', 'lines');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Invoice', $id->className);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new PropertyNodeId('App\Domain', 'Invoice', 'lines'))->toString(),
            PropertyNodeId::of('App\Domain\Invoice', 'lines')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', PropertyNodeId::of('Invoice', 'lines')->namespace);
    }
}
