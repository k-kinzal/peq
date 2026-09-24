<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\Graph\Direction;

/**
 * Selects an explicit coordinate or the direction's first/last source occurrence.
 */
final class Selection
{
    /**
     * Includes retained syntax so an unanalysed final occurrence is never silently skipped.
     *
     * @return list<string>
     *
     * @throws InspectionException If no source occurrence matches the selector
     */
    public function roots(DependencyGraph $graph, ?int $line, ?string $variable, ?int $column, Direction $direction): array
    {
        if ($line !== null) {
            return $graph->select($line, $variable, $column);
        }
        if ($variable === null) {
            throw new InspectionException('Specify a variable in the target (Class:method$variable), --variable, or --line.');
        }
        $occurrences = $this->occurrences($graph, '$'.ltrim($variable, '$'), $column);
        usort($occurrences, static fn (Occurrence $left, Occurrence $right): int => [$left->line, $left->column] <=> [$right->line, $right->column]);
        if ($occurrences === []) {
            throw new InspectionException('No $'.ltrim($variable, '$').' occurrence in '.$graph->target.'.');
        }
        $chosen = $direction === Direction::UsedBy ? $occurrences[0] : $occurrences[array_key_last($occurrences)];
        if ($direction === Direction::Uses && $column === null) {
            $chosen = $this->lastResult($graph, $chosen);
        }
        $roots = $graph->select($chosen->line, $variable, $chosen->column);
        $preferred = $direction === Direction::Uses ? ['write', 'parameter', 'property-declaration'] : ['read', 'parameter', 'property-declaration'];
        $specific = array_values(array_filter($roots, static fn (string $id): bool => in_array($graph->nodes[$id]->kind, $preferred, true)));

        return $specific === [] ? $roots : $specific;
    }

    /**
     * Keeps variables from the selected scope, including closure capture declarations.
     *
     * @return list<Occurrence>
     */
    public function occurrences(DependencyGraph $graph, string $name, ?int $column): array
    {
        $occurrences = array_filter($graph->nodes, static fn (Occurrence $node): bool => $node->variable === $name && $graph->selectable($node));
        $hidden = [];
        foreach ($graph->inventory->sites ?? [] as $site) {
            if ($site->source->variable !== $name || str_contains($graph->target, '::$')) {
                continue;
            }
            if ($this->inScope($site, $graph)) {
                $occurrences[] = $site->source;
            } else {
                $hidden[$site->source->line.':'.$site->source->column] = true;
            }
        }

        return array_values(array_filter($occurrences, static fn (Occurrence $node): bool => !isset($hidden[$node->line.':'.$node->column]) && ($column === null || $node->column === $column)));
    }

    /**
     * Nested callable parameters/bodies and class members do not share local names.
     */
    public function inScope(\App\Analyzer\ExperimentAnalyzer\Structure\Site $site, DependencyGraph $graph): bool
    {
        while ($site->parent !== null) {
            $parent = $graph->inventory->sites[$site->parent] ?? null;
            if ($parent === null) {
                break;
            }
            if ($parent->parent !== null && in_array($parent->syntax, ['Stmt_Function', 'Stmt_ClassMethod', 'Stmt_Class', 'Stmt_Interface', 'Stmt_Trait', 'Stmt_Enum', 'Expr_Closure', 'Expr_ArrowFunction'], true)) {
                if ($parent->syntax !== 'Expr_Closure' || !str_starts_with($site->role, 'uses[')) {
                    return false;
                }
            }
            $site = $parent;
        }

        return true;
    }

    /**
     * A final self-assignment selects its completed write, not the earlier RHS read.
     */
    public function lastResult(DependencyGraph $graph, Occurrence $chosen): Occurrence
    {
        $sites = $graph->inventory->sites ?? [];
        foreach ($sites as $site) {
            if ($site->source->variable !== $chosen->variable || $site->source->line !== $chosen->line || $site->source->column !== $chosen->column) {
                continue;
            }
            while ($site->parent !== null && isset($sites[$site->parent])) {
                $site = $sites[$site->parent];
                if ($site->syntax === 'Expr_Assign' || str_starts_with($site->syntax, 'Expr_AssignOp_')) {
                    foreach ($sites as $target) {
                        if ($target->parent === $site->source->id && $target->role === 'var' && $target->source->variable === $chosen->variable) {
                            $chosen = $target->source;
                        }
                    }
                }
            }

            break;
        }

        return $chosen;
    }
}
