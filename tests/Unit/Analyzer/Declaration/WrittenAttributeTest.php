<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration;

use App\Analyzer\Declaration\ExpressionText;
use App\Analyzer\Declaration\WrittenAttribute;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WrittenAttribute::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(ExpressionText::class)]
#[Small]
final class WrittenAttributeTest extends TestCase
{
    public function testUsageRecordsTheAttributeUnderTheNameItResolvesTo(): void
    {
        $written = new Attribute(new Name('Route'));

        self::assertSame('App\Http\Route', WrittenAttribute::usage($written, 'App\Http\Route')->name);
    }

    public function testUsageKeepsTheArgumentsTheAttributeWasGiven(): void
    {
        $written = new Attribute(new Name('Route'), [
            new Arg(new String_('/users')),
            new Arg(new String_('GET'), name: new Identifier('method')),
        ]);

        self::assertSame(["'/users'", "method: 'GET'"], WrittenAttribute::usage($written, 'App\Http\Route')->arguments);
    }

    public function testUsageOfAnAttributeWrittenWithoutArgumentsRecordsNone(): void
    {
        $written = new Attribute(new Name('Override'));

        self::assertSame([], WrittenAttribute::usage($written, 'Override')->arguments);
    }
}
