<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\NodeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NodeKind::class)]
#[Small]
final class NodeKindTest extends TestCase
{
    public function testEveryKindOfSymbolPhpDeclaresHasACase(): void
    {
        self::assertCount(11, NodeKind::cases());
    }

    public function testClassIsSpelledWithoutThePhpKeyword(): void
    {
        self::assertSame('class', NodeKind::Klass->value);
    }

    public function testEveryCaseIsSpelledExactlyOnce(): void
    {
        $values = array_map(static fn (NodeKind $kind): string => $kind->value, NodeKind::cases());

        self::assertSame($values, array_values(array_unique($values)));
    }

    public function testAWrittenKindResolvesToItsCase(): void
    {
        self::assertSame(NodeKind::EnumCase, NodeKind::from('enum_case'));
    }
}
