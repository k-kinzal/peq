<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\TraitMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TraitMethod::class)]
#[Small]
final class TraitMethodTest extends TestCase
{
    public function testRemembersWhichTraitWritesTheMethod(): void
    {
        self::assertSame('App\Shared', (new TraitMethod('App\Shared', false))->declaringTrait);
    }

    public function testRemembersThatATraitWroteTheMethod(): void
    {
        self::assertFalse((new TraitMethod('App\Shared', false))->demanded);
    }

    public function testRemembersThatATraitOnlyDemandedTheMethod(): void
    {
        self::assertTrue((new TraitMethod('App\Shared', true))->demanded);
    }
}
