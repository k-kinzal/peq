<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EnumCaseNodeIdTest extends TestCase
{
    #[Test]
    public function testConstruct(): void
    {
        $id = new EnumCaseNodeId('App\Service', 'MyEnum', 'CASE_ONE');

        self::assertSame('App\Service', $id->namespace);
        self::assertSame('MyEnum', $id->enumName);
        self::assertSame('CASE_ONE', $id->caseName);
    }

    #[Test]
    public function testFullQualifiedName(): void
    {
        $id = new EnumCaseNodeId('App\Service', 'MyEnum', 'CASE_ONE');

        self::assertSame('App\Service\MyEnum::CASE_ONE', $id->fullQualifiedName());
    }

    #[Test]
    public function testToString(): void
    {
        $id = new EnumCaseNodeId('App\Service', 'MyEnum', 'CASE_ONE');

        self::assertSame('App\Service\MyEnum::CASE_ONE', $id->toString());
        self::assertSame('App\Service\MyEnum::CASE_ONE', (string) $id);
    }

    #[Test]
    public function testMagicGet(): void
    {
        $id = new EnumCaseNodeId('App', 'Test', 'CASE');

        self::assertSame('App\Test::CASE', $id->fullQualifiedName);
    }
}
