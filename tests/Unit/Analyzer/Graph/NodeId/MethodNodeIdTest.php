<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\MethodNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MethodNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new MethodNodeId('App\Service', 'MyClass', 'myMethod');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyClass', $id->className);
        self::assertSame('myMethod', $id->methodName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new MethodNodeId('App\Service', 'MyClass', 'myMethod');

        self::assertSame('App\Service\MyClass::myMethod', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new MethodNodeId('App\Service', 'MyClass', 'myMethod');

        self::assertSame('App\Service\MyClass::myMethod', $id->toString());
        self::assertSame('App\Service\MyClass::myMethod', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new MethodNodeId('App', 'Test', 'method');

        self::assertSame('App\Test::method', $id->fullQualifiedName);
    }
}
