<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Call;

use App\Analyzer\Graph\Call\CallArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CallArgument::class)]
#[UsesNamespace('App')]
#[Small]
final class CallArgumentTest extends TestCase
{
    public function testWrittenArgumentsKeepNamesAndUnpackingWithoutClaimingAnEvaluatedValue(): void
    {
        $named = new CallArgument('mode: Config::MODE', 'mode');
        $spread = new CallArgument('...$args', unpack: true);
        $literal = new CallArgument('42', type: 'int');

        self::assertSame('mode: Config::MODE', $named->text);
        self::assertSame('mode', $named->name);
        self::assertNull($named->type);
        self::assertFalse($named->unpack);
        self::assertSame('...$args', $spread->text);
        self::assertTrue($spread->unpack);
        self::assertNull($spread->name);
        self::assertSame('int', $literal->type);
    }
}
