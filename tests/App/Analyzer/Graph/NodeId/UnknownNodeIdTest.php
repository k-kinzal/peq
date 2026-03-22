<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\UnknownNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UnknownNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new UnknownNodeId('UnknownType');

        self::assertSame('UnknownType', $id->name);
    }

    #[Test]
    public function testToString(): void
    {
        $id = new UnknownNodeId('UnknownType');

        self::assertSame('UnknownType', $id->toString());
        self::assertSame('UnknownType', (string) $id);
    }
}
