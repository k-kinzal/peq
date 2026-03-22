<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\FunctionNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FunctionNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new FunctionNodeId('App\Service', 'myFunction');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('myFunction', $id->functionName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new FunctionNodeId('App\Service', 'myFunction');

        self::assertSame('App\Service\myFunction', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new FunctionNodeId('App\Service', 'myFunction');

        self::assertSame('App\Service\myFunction', $id->toString());
        self::assertSame('App\Service\myFunction', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new FunctionNodeId('App', 'test');

        self::assertSame('App\test', $id->fullQualifiedName);
    }
}
