<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InterfaceNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class InterfaceNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Payable', (new InterfaceNodeId('App\Domain', 'Payable'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Payable', (new InterfaceNodeId('', 'Payable'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = InterfaceNodeId::of('App\Domain\Payable');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Payable', $id->interfaceName);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new InterfaceNodeId('App\Domain', 'Payable'))->toString(),
            InterfaceNodeId::of('App\Domain\Payable')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', InterfaceNodeId::of('Payable')->namespace);
    }
}
