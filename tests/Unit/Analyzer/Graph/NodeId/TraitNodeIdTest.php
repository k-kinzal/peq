<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\TraitNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TraitNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class TraitNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Timestamped', (new TraitNodeId('App\Domain', 'Timestamped'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Timestamped', (new TraitNodeId('', 'Timestamped'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = TraitNodeId::of('App\Domain\Timestamped');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Timestamped', $id->traitName);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new TraitNodeId('App\Domain', 'Timestamped'))->toString(),
            TraitNodeId::of('App\Domain\Timestamped')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', TraitNodeId::of('Timestamped')->namespace);
    }
}
