<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\Flow\Truth::class)]
final class TruthTest extends TestCase
{
    public function testOfUsesPhpStringTruthinessWithoutGuessingAVariableValue(): void
    {
        self::assertFalse(\App\Analyzer\ExperimentAnalyzer\Flow\Truth::of(new \PhpParser\Node\Scalar\String_('0')));
        self::assertTrue(\App\Analyzer\ExperimentAnalyzer\Flow\Truth::of(new \PhpParser\Node\Scalar\String_('false')));
        self::assertNull(\App\Analyzer\ExperimentAnalyzer\Flow\Truth::of(new \PhpParser\Node\Expr\Variable('flag')));
    }
}
