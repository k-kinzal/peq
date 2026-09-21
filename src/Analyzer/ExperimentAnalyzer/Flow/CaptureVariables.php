<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Finds lexical captures without treating a nested callable's parameters as outer reads.
 */
final class CaptureVariables
{
    /**
     * @param list<string> $bound Names bound by enclosing arrow parameters
     *
     * @return list<Expr\Variable>
     */
    public function find(Node $node, array $bound = []): array
    {
        if ($node instanceof Expr\Variable) {
            return is_string($node->name) && !in_array($node->name, $bound, true) ? [$node] : [];
        }
        if ($node instanceof Expr\Closure) {
            $variables = [];
            foreach ($node->uses as $use) {
                array_push($variables, ...$this->find($use->var, $bound));
            }

            return $variables;
        }
        if ($node instanceof Expr\ArrowFunction) {
            foreach ($node->params as $parameter) {
                if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                    $bound[] = $parameter->var->name;
                }
            }

            return $this->find($node->expr, $bound);
        }
        $variables = [];
        foreach ($node->getSubNodeNames() as $name) {
            $children = get_object_vars($node)[$name] ?? null;
            foreach (is_array($children) ? $children : [$children] as $child) {
                if ($child instanceof Node && !$child instanceof Node\Stmt\ClassLike) {
                    array_push($variables, ...$this->find($child, $bound));
                }
            }
        }

        return $variables;
    }
}
