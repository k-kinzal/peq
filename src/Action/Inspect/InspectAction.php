<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Config\AnalyzerKind;

/**
 * Runs an inspection: builds the dependency graph and locates the symbol in it.
 *
 * This is where the configuration is turned into collaborators. Choosing the
 * analyzer is a closed decision over AnalyzerKind, and each arm is handed only the
 * settings that analyzer needs — an analyzer is told its parameters rather than
 * given the application's configuration to read, which is what keeps the analyzers
 * independent of how peq happens to be configured.
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

        $analyzer = match ($config->analyzer) {
            AnalyzerKind::PhpStan => new PhpStanAnalyzer(
                includes: $config->includes,
                excludes: $config->excludes,
            ),
            AnalyzerKind::Native => new NativeAnalyzer(
                includes: $config->includes,
                excludes: $config->excludes,
            ),
            AnalyzerKind::Debug => new DebugAnalyzer(
                seed: $config->debug->seed,
                depth: $config->debug->depth,
            ),
        };

        $graph = $analyzer->analyze($config->basePath);
        $symbol = $graph->nodeNamed($input->target);
        if ($symbol === null) {
            throw SymbolNotFoundException::forTarget($input->target);
        }

        return new InspectActionOutput(graph: $graph, symbol: $symbol);
    }
}
