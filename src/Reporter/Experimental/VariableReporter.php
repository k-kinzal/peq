<?php

declare(strict_types=1);

namespace App\Reporter\Experimental;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Config\OutputFormat;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\MermaidRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Formats an already selected graph; format choice never changes its dependencies.
 */
final class VariableReporter
{
    /**
     * Writes the same selected graph in the requested format.
     *
     * @throws JsonException If the requested analysis or encoding is rejected
     */
    public function report(Slice $slice, OutputFormat $format, OutputInterface $output): void
    {
        $text = match ($format) {
            OutputFormat::Json => json_encode([
                'experimental' => true, 'schemaVersion' => 2,
                'analysis' => $slice->analysis, 'structure' => $slice->structure, 'provenance' => $slice->provenance,
                'semantics' => 'Local source-origin analysis under normal PHP expression completion. Runtime values and path feasibility are not proved. Structure is a complete lexical inventory, not execution order. possible-input and unresolved-region edges are evidence across Unknown, not proven dependencies. analysis.complete covers the callable and selected traversal; it does not prove runtime safety.',
                'target' => $slice->target, 'file' => $slice->file, 'direction' => $slice->direction->value,
                'roots' => $slice->roots, 'nodes' => $slice->nodes, 'edges' => $slice->edges, 'diagnostics' => $slice->diagnostics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            OutputFormat::Tree => (new VariableTree())->render($slice),
            OutputFormat::Table => $this->table($slice),
            OutputFormat::Dot => $this->dot($slice),
            OutputFormat::Graph, OutputFormat::Mermaid => $this->diagram($slice, $format),
        };
        $output->writeln($text, OutputInterface::OUTPUT_RAW);
        if ($format !== OutputFormat::Json) {
            $prefix = $format === OutputFormat::Dot ? '// ' : ($format === OutputFormat::Mermaid ? '%% ' : '');
            $output->writeln($prefix.'Analysis: '.$slice->analysis->status.'; complete: '.($slice->analysis->complete ? 'yes' : 'no'), OutputInterface::OUTPUT_RAW);
            foreach ($slice->diagnostics as $diagnostic) {
                $output->writeln($prefix.$diagnostic, OutputInterface::OUTPUT_RAW);
            }
        }
    }

    /**
     * Renders a selected graph with the existing diagram infrastructure.
     */
    public function diagram(Slice $slice, OutputFormat $format): string
    {
        $diagram = new Diagram();
        foreach ($slice->nodes as $node) {
            $diagram->add(new DiagramNode($node->id, $node->kind, $slice->file.':'.$node->line.':'.$node->column));
        }
        foreach ($slice->edges as $edge) {
            $diagram->relate(new DiagramEdge($edge->from, $edge->to, $edge->kind.($edge->branch === null ? '' : ' ('.$edge->branch.')')));
        }
        $renderer = $format === OutputFormat::Mermaid ? new MermaidRenderer() : new TerminalRenderer((new Terminal())->getWidth());

        $output = new BufferedOutput();
        $renderer->render($diagram, $output);

        return rtrim($output->fetch());
    }

    /**
     * Writes one row per dependency of the selected graph.
     */
    public function table(Slice $slice): string
    {
        $lines = ['FROM | DEPENDENCY | TO'];
        foreach ($slice->edges as $edge) {
            $lines[] = $edge->from.' | '.$edge->kind.($edge->branch === null ? '' : ' ('.$edge->branch.')').' | '.$edge->to;
        }
        $connected = [];
        foreach ($slice->edges as $edge) {
            $connected[$edge->from] = true;
            $connected[$edge->to] = true;
        }
        foreach ($slice->nodes as $node) {
            if (!isset($connected[$node->id])) {
                $lines[] = $node->id.' | (no selected edges) |';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Writes source occurrence identities and labeled dependencies as Graphviz.
     *
     * @throws JsonException If the requested analysis or encoding is rejected
     */
    public function dot(Slice $slice): string
    {
        $lines = ['digraph experimental {'];
        foreach ($slice->nodes as $node) {
            $lines[] = '  '.json_encode($node->id, JSON_THROW_ON_ERROR).';';
        }
        foreach ($slice->edges as $edge) {
            $lines[] = '  '.json_encode($edge->from, JSON_THROW_ON_ERROR).' -> '.json_encode($edge->to, JSON_THROW_ON_ERROR)
                .' [label='.json_encode($edge->kind.($edge->branch === null ? '' : ' ('.$edge->branch.')'), JSON_THROW_ON_ERROR).'];';
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }
}
