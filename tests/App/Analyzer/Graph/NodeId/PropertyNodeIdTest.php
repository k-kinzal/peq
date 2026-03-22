<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PropertyNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new PropertyNodeId('App\Service', 'MyClass', 'myProperty');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyClass', $id->className);
        self::assertSame('myProperty', $id->propertyName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new PropertyNodeId('App\Service', 'MyClass', 'myProperty');

        self::assertSame('App\Service\MyClass::myProperty', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new PropertyNodeId('App\Service', 'MyClass', 'myProperty');

        self::assertSame('App\Service\MyClass::myProperty', $id->toString());
        self::assertSame('App\Service\MyClass::myProperty', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new PropertyNodeId('App', 'Test', 'prop');

        self::assertSame('App\Test::prop', $id->fullQualifiedName);
    }
}
