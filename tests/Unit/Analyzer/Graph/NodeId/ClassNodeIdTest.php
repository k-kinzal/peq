<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\ClassNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class ClassNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\Invoice', (new ClassNodeId('App\Domain', 'Invoice'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('Invoice', (new ClassNodeId('', 'Invoice'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = ClassNodeId::of('App\Domain\Invoice');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('Invoice', $id->className);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new ClassNodeId('App\Domain', 'Invoice'))->toString(),
            ClassNodeId::of('App\Domain\Invoice')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', ClassNodeId::of('Invoice')->namespace);
    }
}
