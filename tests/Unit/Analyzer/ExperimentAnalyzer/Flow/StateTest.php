<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\Flow\State::class)]
final class StateTest extends TestCase
{
    public function testJoinUnionsDefinitionsAndKeepsOnlyCommonGuards(): void
    {
        $left = new \App\Analyzer\ExperimentAnalyzer\Flow\State(['$x' => ['first' => true]], ['guard' => 'truthy', 'outer' => 'truthy']);
        $right = new \App\Analyzer\ExperimentAnalyzer\Flow\State(['$x' => ['second' => true]], ['guard' => 'falsy', 'outer' => 'truthy']);
        $joined = \App\Analyzer\ExperimentAnalyzer\Flow\State::join([$left, $right]);
        self::assertSame(['$x' => ['first' => true, 'second' => true]], $joined->definitions);
        self::assertSame(['outer' => 'truthy'], $joined->controls);
        self::assertSame(['$x' => ['first' => true]], $left->definitions);
    }

    public function testContinueWithExcludesTerminatedBranches(): void
    {
        $live = new \App\Analyzer\ExperimentAnalyzer\Flow\State(['$x' => ['live' => true]]);
        $dead = new \App\Analyzer\ExperimentAnalyzer\Flow\State(['$x' => ['dead' => true]], reachable: false);
        $result = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        $result->continueWith([$live, $dead]);
        self::assertSame(['$x' => ['live' => true]], $result->definitions);
        self::assertTrue($result->reachable);
    }

    public function testSameDefinitionsIgnoresSetInsertionOrder(): void
    {
        $left = new \App\Analyzer\ExperimentAnalyzer\Flow\State(['$x' => ['a' => true, 'b' => true]]);
        $right = new \App\Analyzer\ExperimentAnalyzer\Flow\State(['$x' => ['b' => true, 'a' => true]]);
        self::assertTrue($left->sameDefinitions($right));
        self::assertFalse($left->sameDefinitions(new \App\Analyzer\ExperimentAnalyzer\Flow\State()));
    }
}
