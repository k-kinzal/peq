<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Config\Config;
use App\Config\OutputFormat;
use App\Gql\Element\ElementGraph;
use App\Reporter\Diagram\MermaidRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use Symfony\Component\Console\Terminal;

/**
 * Chooses and assembles the reporter a configuration asks a query for.
 *
 * The formats are the same closed set the inspection uses, because they answer the
 * same question about the reader rather than about the command: who is going to read
 * this, and in what. A format added without a reporter here is a static analysis
 * error rather than a surprise at the moment someone asks for it.
 *
 * What differs is what each format means for a table rather than for a walk. A table
 * is written as a table; a drawing is of the piece of graph the answer holds; and a
 * tree is of the paths the query returned. A nonempty result without the required
 * elements is rejected with guidance on choosing a format or returning elements.
 */
final class QueryReporterFactory
{
    /**
     * Builds the reporter for a configuration.
     *
     * The two formats that draw the answer are given the graph it came from, so that
     * they can show the relations between the symbols a query found rather than only
     * the ones it happened to bind. The other three have no use for it.
     *
     * @param Config            $config The application configuration
     * @param null|ElementGraph $graph  The graph the answer came from, or null when there is none
     *
     * @example A configuration that asks for a table is given one
     *     $config = \App\Config\Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'table']);
     *     (new \App\Reporter\Query\QueryReporterFactory())->create($config) instanceof \App\Reporter\Query\TableWriter // => true
     * @example One that asks for a drawing is given that instead
     *     $config = \App\Config\Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native', 'output' => 'graph']);
     *     (new \App\Reporter\Query\QueryReporterFactory())->create($config) instanceof \App\Reporter\Query\DiagramWriter // => true
     *
     * @return QueryReporter A reporter writing the answer in the format the configuration asks for
     */
    public function create(Config $config, ?ElementGraph $graph = null): QueryReporter
    {
        return match ($config->output) {
            OutputFormat::Table => new TableWriter(),
            OutputFormat::Json => new JsonWriter(),
            OutputFormat::Graph => new DiagramWriter($graph, new TerminalRenderer((new Terminal())->getWidth())),
            OutputFormat::Mermaid => new DiagramWriter($graph, new MermaidRenderer()),
            OutputFormat::Dot => new DotWriter($graph),
            OutputFormat::Tree => new TreeWriter(),
        };
    }
}
