<?php

declare(strict_types=1);

namespace Tests\Unit\Action;

use App\Action\Inspect\InspectAction;
use App\Action\Inspect\InspectActionInput;
use App\Action\Inspect\InspectActionOutput;
use App\Analyzer\Graph\Graph;
use App\Config\AnalyzerType;
use App\Config\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class InspectActionTest extends TestCase
{
    #[Test]
    public function itExecutesAnalysisAndReturnsOutput(): void
    {
        $action = new InspectAction();
        $config = new Config(
            basePath: __DIR__,
            direction: 'uses',
            type: AnalyzerType::Debug,
        );
        $input = new InspectActionInput(config: $config, target: 'Target');
        $output = $action->execute($input);

        self::assertInstanceOf(InspectActionOutput::class, $output);
        self::assertInstanceOf(Graph::class, $output->graph);
    }
}
