<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectActionInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Config\SampleConfig;

/**
 * @internal
 */
#[CoversClass(InspectActionInput::class)]
#[UsesClass(\App\Config\Config::class)]
#[UsesClass(\App\Config\DebugAnalyzerConfig::class)]
#[Small]
final class InspectActionInputTest extends TestCase
{
    public function testTheConfigurationIsCarriedThroughUnchanged(): void
    {
        $config = SampleConfig::generated();

        self::assertSame($config, (new InspectActionInput($config, 'App\Domain\Invoice'))->config);
    }

    public function testTheTargetIsCarriedAsTheUserWroteIt(): void
    {
        self::assertSame(
            'App\Domain\Invoice::total',
            (new InspectActionInput(SampleConfig::generated(), 'App\Domain\Invoice::total'))->target,
        );
    }

    public function testTheTargetIsNotResolvedBeforeTheInspectionRuns(): void
    {
        self::assertSame(
            'App\Domain\NeverAnalysed',
            (new InspectActionInput(SampleConfig::generated(), 'App\Domain\NeverAnalysed'))->target,
        );
    }
}
