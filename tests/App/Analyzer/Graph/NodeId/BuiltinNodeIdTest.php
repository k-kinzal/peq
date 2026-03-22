<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BuiltinNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new BuiltinNodeId('Builtin', 'StringType');

        self::assertSame('Builtin', $id->namespace);
        self::assertSame('StringType', $id->name);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new BuiltinNodeId('Builtin', 'StringType');

        self::assertSame('Builtin\StringType', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new BuiltinNodeId('Builtin', 'StringType');

        self::assertSame('Builtin\StringType', $id->toString());
        self::assertSame('Builtin\StringType', (string) $id);
    }

    #[Test]
    public function testMagicGetReturnsFullQualifiedName(): void
    {
        $id = new BuiltinNodeId('Builtin', 'string');
        self::assertSame('Builtin\string', $id->fullQualifiedName);
    }

    #[Test]
    public function testMagicGetThrowsExceptionForUndefinedProperty(): void
    {
        $id = new BuiltinNodeId('Builtin', 'string');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Undefined property: undefined');

        /** @phpstan-ignore property.notFound */
        $unused = $id->undefined;
    }
}
