<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectActionInput;
use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\DebugAnalyzerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InspectActionInput::class)]
#[UsesClass(Config::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[Small]
final class InspectActionInputTest extends TestCase
{
    public function testTheConfigurationIsCarriedThroughUnchanged(): void
    {
        $config = new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42));

        self::assertSame($config, (new InspectActionInput($config, 'App\Domain\Invoice'))->config);
    }

    public function testTheTargetIsCarriedAsTheUserWroteIt(): void
    {
        self::assertSame(
            'App\Domain\Invoice::total',
            (new InspectActionInput(new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)), 'App\Domain\Invoice::total'))->target,
        );
    }

    public function testTheTargetIsNotResolvedBeforeTheInspectionRuns(): void
    {
        self::assertSame(
            'App\Domain\NeverAnalysed',
            (new InspectActionInput(new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)), 'App\Domain\NeverAnalysed'))->target,
        );
    }
}
