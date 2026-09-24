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
        $target = VariableTarget::parse($input->target, $input->variable);
        if ($input->line === null && $target->variable === null) {
            throw new InspectionRejected('Specify a variable in the target (Class:method$variable), --variable, or --line.');
        }

        try {
            $graph = (new ExperimentAnalyzer($config->includes, $config->excludes, $config->phpVersion?->id))
                ->inspect($config->basePath, $target->symbol)
            ;
            $roots = (new \App\Analyzer\ExperimentAnalyzer\DataFlow\Selection())->roots($graph, $input->line, $target->variable, $input->column, $config->direction);
            $graph->provenance += ['line' => $input->line, 'variable' => $target->variable, 'column' => $input->column, 'direction' => $config->direction->value, 'level' => $config->level, 'requestedTarget' => $input->target, 'selection' => $input->line === null ? ($config->direction === \App\Analyzer\Graph\Direction::Uses ? 'last-occurrence' : 'first-occurrence') : 'explicit-line'];

            return Slice::of($graph, $roots, $config->direction, $config->level);
        } catch (InspectionException $error) {
            throw new InspectionRejected($error->getMessage(), previous: $error);
        }
    }
}
