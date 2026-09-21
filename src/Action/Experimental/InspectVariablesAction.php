<?php

declare(strict_types=1);

namespace App\Action\Experimental;

use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer;

/**
 * Chooses the isolated experimental engine and selects one local dependency slice.
 */
final class InspectVariablesAction
{
    /**
     * Builds and selects the requested experimental dependency graph.
     *
     * @throws InspectionRejected If the requested analysis or encoding is rejected
     */
    public function execute(InspectVariablesInput $input): Slice
    {
        $config = $input->config;

        try {
            $graph = (new ExperimentAnalyzer($config->includes, $config->excludes, $config->phpVersion?->id))
                ->inspect($config->basePath, $input->target)
            ;
            $roots = $graph->select($input->line, $input->variable, $input->column);

            return Slice::of($graph, $roots, $config->direction, $config->level);
        } catch (InspectionException $error) {
            throw new InspectionRejected($error->getMessage(), previous: $error);
        }
    }
}
