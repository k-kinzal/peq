<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\ConstantNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConstantNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new ConstantNodeId('App\Service', 'MyClass', 'MY_CONSTANT');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyClass', $id->className);
        self::assertSame('MY_CONSTANT', $id->constantName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new ConstantNodeId('App\Service', 'MyClass', 'MY_CONSTANT');

        self::assertSame('App\Service\MyClass::MY_CONSTANT', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new ConstantNodeId('App\Service', 'MyClass', 'MY_CONSTANT');

        self::assertSame('App\Service\MyClass::MY_CONSTANT', $id->toString());
        self::assertSame('App\Service\MyClass::MY_CONSTANT', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new ConstantNodeId('App', 'Test', 'CONST');

        self::assertSame('App\Test::CONST', $id->fullQualifiedName);
    }
}
