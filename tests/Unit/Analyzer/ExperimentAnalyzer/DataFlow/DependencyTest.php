<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency::class)]
final class DependencyTest extends TestCase
{
    public function testControlEdgeRetainsTheBranchPolarity(): void
    {
        $edge = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency('write', 'test', 'control', 'falsy');
        self::assertSame('falsy', $edge->branch);
        self::assertSame('write', $edge->from);
        self::assertSame('test', $edge->to);
    }
}
