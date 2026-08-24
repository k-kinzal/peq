<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Config\Config;
use App\Reporter\Traversal\DepthFirstTraversal;
use App\Reporter\TreeReporter\TreeReporter;
use App\Reporter\TreeReporter\TreeReporterOptions;

/**
 * Chooses and assembles the reporter a configuration asks for.
 *
 * Which reporter runs, how deep it goes and which way it reads the graph all follow
 * from the configuration. Deciding that is neither the command's business — it only
 * parses input and hands over output — nor the use case's, which knows nothing about
 * a console. It lives here, so that adding a second output format is one arm in one
 * place rather than a branch inside a command.
 */
final class ReporterFactory
{
    /**
     * Builds the reporter for a configuration.
     *
     * @param Config $config The application configuration
     *
     * @return Reporter A reporter reading the graph the way the configuration asks
     */
    public function create(Config $config): Reporter
    {
        return new TreeReporter(
            options: new TreeReporterOptions(level: $config->level),
            traversal: new DepthFirstTraversal($config->direction),
        );
    }
}
