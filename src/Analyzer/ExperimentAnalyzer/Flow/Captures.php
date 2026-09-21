<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * A closure captures now; its body is not executed in the enclosing scope.
 */
final readonly class Captures
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(private Expressions $expressions) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Expr\ArrowFunction|Expr\Closure $node, State $state): string
    {
        $inputs = [];
        $variables = $node instanceof Expr\Closure
            ? array_map(static fn (Expr\ClosureUse $use): Expr\Variable => $use->var, $node->uses)
            : (new CaptureVariables())->find($node);
        if ($node instanceof Expr\Closure && !$node->static) {
            $receiver = (new NodeFinder())->findFirst($node->stmts, static fn (Node $child): bool => $child instanceof Expr\Variable && $child->name === 'this');
            if ($receiver instanceof Expr\Variable) {
                $variables[] = $receiver;
            }
        }
        $parameters = [];
        foreach ($node->params as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $parameters[] = $parameter->var->name;
            }
        }
        foreach ($variables as $variable) {
            if (is_string($variable->name) && !in_array($variable->name, $parameters, true)) {
                $inputs[] = $this->expressions->recording->value($variable, 'capture', array_keys($state->definitions['$'.$variable->name] ?? []), $state, '$'.$variable->name);
            }
        }
        $this->expressions->recording->graph->diagnose($node, 'Closure boundary: captures are recorded; the nested body is not executed in this scope.');

        return $this->expressions->recording->value($node, 'closure', $inputs, $state);
    }
}
