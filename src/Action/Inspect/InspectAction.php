<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Config\AnalyzerType;

/**
 * Executes the inspection process using the specified analyzer.
 */
final class InspectAction
{
    /**
     * Executes the inspection action.
     *
     * @param InspectActionInput $input the input data for the action
     *
     * @return InspectActionOutput the output data from the action
     */
    public function execute(InspectActionInput $input): InspectActionOutput
    {
        $config = $input->config;

        $analyzer = match ($config->type) {
            AnalyzerType::Debug => DebugAnalyzer::create($config),
        };
        $graph = $analyzer->analyze($config->basePath);

        return new InspectActionOutput($graph);
    }
}
