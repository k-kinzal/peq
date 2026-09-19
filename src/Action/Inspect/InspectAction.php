<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Action\AnalyzerChoice;

/**
 * Runs an inspection: builds the dependency graph and locates the symbol in it.
 *
 * Locating the symbol belongs here rather than in the command, because it is a
 * question about the graph and its answer decides whether the inspection succeeded.
 */
final class InspectAction
{
    /**
     * Builds the graph for the configured path and resolves the requested symbol.
     *
     * @param InspectActionInput $input The configuration and the symbol to inspect
     *
     * @return InspectActionOutput The graph and the node the symbol resolved to
     *
     * @throws SymbolNotFoundException If the analyzed graph holds no symbol of that name
     */
    public function execute(InspectActionInput $input): InspectActionOutput
    {
        $config = $input->config;
        $graph = AnalyzerChoice::forConfig($config)->analyze($config->basePath);
        $symbol = $graph->nodeNamed($input->target);
        if ($symbol === null) {
            throw SymbolNotFoundException::forTarget($input->target);
        }

        return new InspectActionOutput(graph: $graph, symbol: $symbol);
    }
}
