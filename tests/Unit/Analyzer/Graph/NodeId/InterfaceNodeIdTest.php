<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class InterfaceNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new InterfaceNodeId('App\Service', 'MyInterface');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyInterface', $id->interfaceName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new InterfaceNodeId('App\Service', 'MyInterface');

        self::assertSame('App\Service\MyInterface', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new InterfaceNodeId('App\Service', 'MyInterface');

        self::assertSame('App\Service\MyInterface', $id->toString());
        self::assertSame('App\Service\MyInterface', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new InterfaceNodeId('App', 'Test');

        self::assertSame('App\Test', $id->fullQualifiedName);
    }
}
