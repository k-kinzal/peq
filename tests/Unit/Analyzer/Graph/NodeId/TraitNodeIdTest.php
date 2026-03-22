<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\TraitNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TraitNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new TraitNodeId('App\Service', 'MyTrait');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyTrait', $id->traitName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new TraitNodeId('App\Service', 'MyTrait');

        self::assertSame('App\Service\MyTrait', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new TraitNodeId('App\Service', 'MyTrait');

        self::assertSame('App\Service\MyTrait', $id->toString());
        self::assertSame('App\Service\MyTrait', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new TraitNodeId('App', 'Test');

        self::assertSame('App\Test', $id->fullQualifiedName);
    }
}
