<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Config\Config;
use App\Config\OutputFormat;
use App\Reporter\DotReporter\DotReporter;
use App\Reporter\JsonReporter\JsonReporter;
use App\Reporter\TableReporter\TableReporter;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;

/**
 * Chooses and assembles the reporter a configuration asks for.
 *
 * Which reporter runs, how deep it goes and which way it reads the graph all follow
 * from the configuration. Deciding that is neither the command's business — it only
 * parses input and hands over output — nor the use case's, which knows nothing about
 * a console. It lives here, so that adding an output format is one arm in one place
 * rather than a branch inside a command.
 *
 * The arm is chosen by a match over a closed set of formats, which is what makes a
 * format that exists but is unreachable impossible: a case added to OutputFormat and
 * not answered here is reported by static analysis rather than at the moment someone
 * asks for it.
 */
final class ReporterFactory
{
    /**
     * Builds the reporter for a configuration.
     *
     * Every reporter is given the same two things — the traversal that decides which
     * relations it follows, and the level it stops at — because every format writes
     * the same walk. What differs between them is only how that walk is written down.
     *
     * @param Config $config The application configuration
     *
     * @return Reporter A reporter writing the graph in the format the configuration asks for
     */
    public function create(Config $config): Reporter
    {
        $traversal = new DepthFirstTraversal($config->direction);

        return match ($config->output) {
            OutputFormat::Tree => new TreeReporter(
                options: new TreeReporterOptions(level: $config->level),
                traversal: $traversal,
            ),
            OutputFormat::Json => new JsonReporter(traversal: $traversal, level: $config->level),
            OutputFormat::Dot => new DotReporter(traversal: $traversal, level: $config->level),
            OutputFormat::Table => new TableReporter(traversal: $traversal, level: $config->level),
        };
    }
}
