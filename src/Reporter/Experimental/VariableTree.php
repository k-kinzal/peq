<?php

declare(strict_types=1);

namespace App\Reporter\Experimental;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;

/**
 * Draws references for already drawn nodes so cycles remain finite.
 */
final class VariableTree
{
    /**
     * Draws the selected roots and their dependencies with explicit references.
     */
    public function render(Slice $slice): string
    {
        $edges = [];
        foreach ($slice->edges as $edge) {
            $edges[$edge->from][] = $edge;
        }
        $lines = ['Experimental dependencies: '.$slice->target.' ('.$slice->direction->value.')', $slice->file];
        $seen = [];
        foreach ($slice->roots as $root) {
            $this->draw($root, '', '', $edges, $seen, $lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, list<Dependency>> $edges
     * @param array<string, true>             $seen
     * @param list<string>                    $lines
     */
    public function draw(string $id, string $indent, string $label, array $edges, array &$seen, array &$lines): void
    {
        $lines[] = $indent.$label.$id.(isset($seen[$id]) ? ' [already shown]' : '');
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;
        foreach ($edges[$id] ?? [] as $edge) {
            $label = '['.$edge->kind.($edge->branch === null ? '' : ': '.$edge->branch).'] ';
            $this->draw($edge->to, $indent.'  ', $label, $edges, $seen, $lines);
        }
    }
}
