<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\FunctionNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class FunctionNodeIdTest extends TestCase
{
    public function testToStringJoinsTheParts(): void
    {
        self::assertSame('App\Domain\formatMoney', (new FunctionNodeId('App\Domain', 'formatMoney'))->toString());
    }

    public function testToStringOmitsTheSeparatorWithoutANamespace(): void
    {
        self::assertSame('formatMoney', (new FunctionNodeId('', 'formatMoney'))->toString());
    }

    public function testOfSplitsAFullyQualifiedName(): void
    {
        $id = FunctionNodeId::of('App\Domain\formatMoney');

        self::assertSame('App\Domain', $id->namespace);
        self::assertSame('formatMoney', $id->functionName);
    }

    public function testOfBuildsTheSameIdentifierAsTheConstructor(): void
    {
        self::assertSame(
            (new FunctionNodeId('App\Domain', 'formatMoney'))->toString(),
            FunctionNodeId::of('App\Domain\formatMoney')->toString(),
        );
    }

    public function testOfLeavesTheNamespaceEmptyForAGlobalName(): void
    {
        self::assertSame('', FunctionNodeId::of('formatMoney')->namespace);
    }
}
