<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\SymbolNotFoundException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(SymbolNotFoundException::class)]
#[Small]
final class SymbolNotFoundExceptionTest extends TestCase
{
    public function testForTargetNamesTheSymbolThatResolvedToNothing(): void
    {
        self::assertStringContainsString(
            'App\Domain\NeverAnalysed',
            SymbolNotFoundException::forTarget('App\Domain\NeverAnalysed')->getMessage(),
        );
    }

    public function testForTargetSaysWhereToLookForTheCause(): void
    {
        $message = SymbolNotFoundException::forTarget('App\Domain\NeverAnalysed')->getMessage();

        self::assertStringContainsString('spelling', $message);
        self::assertStringContainsString('analyzed path', $message);
        self::assertStringContainsString('exclude patterns', $message);
    }

    public function testForTargetNamesThePhpVersionTheSourcesWereReadAs(): void
    {
        self::assertStringContainsString(
            'read as PHP 7.1',
            SymbolNotFoundException::forTarget('App\Domain\NeverAnalysed', '7.1')->getMessage(),
        );
    }

    public function testForTargetStillSaysWhereToLookWhenThePhpVersionIsNamed(): void
    {
        $message = SymbolNotFoundException::forTarget('App\Domain\NeverAnalysed', '7.1')->getMessage();

        self::assertStringContainsString('App\Domain\NeverAnalysed', $message);
        self::assertStringContainsString('spelling', $message);
        self::assertStringContainsString('exclude patterns', $message);
    }

    public function testForTargetNamesNoPhpVersionWhenNoneWasSettled(): void
    {
        self::assertStringNotContainsString(
            'read as PHP',
            SymbolNotFoundException::forTarget('App\Domain\NeverAnalysed')->getMessage(),
        );
    }

    public function testAMissingSymbolMustBeHandledRatherThanEscaping(): void
    {
        $families = class_parents(SymbolNotFoundException::forTarget('App\Domain\NeverAnalysed'));

        self::assertNotContains(RuntimeException::class, $families);
        self::assertNotContains(LogicException::class, $families);
    }
}
