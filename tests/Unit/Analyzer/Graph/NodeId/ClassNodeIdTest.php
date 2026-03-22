<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\ClassNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClassNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new ClassNodeId('App\Service', 'MyClass');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyClass', $id->className);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new ClassNodeId('App\Service', 'MyClass');

        self::assertSame('App\Service\MyClass', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new ClassNodeId('App\Service', 'MyClass');

        self::assertSame('App\Service\MyClass', $id->toString());
        self::assertSame('App\Service\MyClass', (string) $id);
    }

    #[Test]
    public function testMagicGetReturnsFullQualifiedName(): void
    {
        $id = new ClassNodeId('App', 'MyClass');
        self::assertSame('App\MyClass', $id->fullQualifiedName);
    }

    #[Test]
    public function testMagicGetThrowsExceptionForUndefinedProperty(): void
    {
        $id = new ClassNodeId('App', 'MyClass');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Undefined property: undefined');

        /** @phpstan-ignore property.notFound */
        $unused = $id->undefined;
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new ClassNodeId('App', 'Test');

        self::assertSame('App\Test', $id->fullQualifiedName);
    }
}
