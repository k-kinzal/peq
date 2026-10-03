<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
#[Large]
final class ExperimentalAnalyzerEquivalenceTest extends TestCase
{
    public function testTheExperimentalSymbolPipelineMatchesNativeOnTheWholeProject(): void
    {
        $path = dirname(__DIR__, 2).'/src';
        $expected = GraphSnapshot::of((new NativeAnalyzer())->analyze($path));
        $actual = GraphSnapshot::of((new ExperimentAnalyzer())->analyze($path));
        self::assertSame($expected->fingerprint(), $actual->fingerprint(), $actual->differenceFrom($expected)->describe());
    }
}
