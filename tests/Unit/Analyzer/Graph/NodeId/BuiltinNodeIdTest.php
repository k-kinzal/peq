<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BuiltinNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class BuiltinNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Money', (new BuiltinNodeId('App\Domain', 'Money'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Money', (new BuiltinNodeId('', 'Money'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = BuiltinNodeId::of('App\Domain\Money');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Money', $id->name);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new BuiltinNodeId('App\Domain', 'Money'))->toString(),
            BuiltinNodeId::of('App\Domain\Money')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', BuiltinNodeId::of('Money')->namespace);
    }
}
