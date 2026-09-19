<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnumCaseNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class EnumCaseNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\InvoiceState::OPEN', (new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('InvoiceState::OPEN', (new EnumCaseNodeId('', 'InvoiceState', 'OPEN'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('InvoiceState', $id->enumName);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new EnumCaseNodeId('App\Domain', 'InvoiceState', 'OPEN'))->toString(),
            EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', EnumCaseNodeId::of('InvoiceState', 'OPEN')->namespace);
    }
}
