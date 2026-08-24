<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\EnumNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnumNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class EnumNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\InvoiceState', (new EnumNodeId('App\Domain', 'InvoiceState'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('InvoiceState', (new EnumNodeId('', 'InvoiceState'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = EnumNodeId::of('App\Domain\InvoiceState');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('InvoiceState', $id->enumName);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new EnumNodeId('App\Domain', 'InvoiceState'))->toString(),
            EnumNodeId::of('App\Domain\InvoiceState')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', EnumNodeId::of('InvoiceState')->namespace);
    }
}
