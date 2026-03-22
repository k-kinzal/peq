<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\EnumNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EnumNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new EnumNodeId('App\Service', 'MyEnum');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyEnum', $id->enumName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new EnumNodeId('App\Service', 'MyEnum');

        self::assertSame('App\Service\MyEnum', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new EnumNodeId('App\Service', 'MyEnum');

        self::assertSame('App\Service\MyEnum', $id->toString());
        self::assertSame('App\Service\MyEnum', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new EnumNodeId('App', 'Test');

        self::assertSame('App\Test', $id->fullQualifiedName);
    }
}
