<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Invocation;

use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\Flow\Expressions;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;

/**
 * compact() reads local variables named by its arguments after evaluating those arguments.
 */
final readonly class CompactVariables
{
    /**
     * @return list<string>
     *
     * @throws InspectionException If a local variable name cannot be determined
     */
    public function inputs(Expr\FuncCall $node, Expressions $expressions, State $state): array
    {
        $names = [];
        foreach ($node->getArgs() as $argument) {
            if ($argument->unpack) {
                throw new InspectionException('Line '.$node->getStartLine().': compact() argument unpacking is not supported.');
            }
            array_push($names, ...$this->names($argument->value));
        }
        $inputs = $expressions->children($node, $state);
        foreach ($names as $name) {
            $variable = '$'.$name->value;
            $recording = $expressions->recording;
            $read = $recording->value($name, 'implicit-read', [], $state, $variable);
            $definitions = $state->definitions[$variable] ?? [$recording->graph->record($name, 'unbound', $variable) => true];
            foreach ($definitions as $definition => $_) {
                $recording->graph->connect($read, $definition, 'reaching-definition');
            }
            $inputs[] = $read;
        }

        return $inputs;
    }

    /**
     * Accepts literal names and nested lists without guessing runtime string values.
     *
     * @return list<String_>
     *
     * @throws InspectionException If names require evaluating an expression or array keys
     */
    public function names(Expr $node): array
    {
        if ($node instanceof String_) {
            return [$node];
        }
        if ($node instanceof Expr\Array_) {
            $names = [];
            foreach ($node->items as $item) {
                if ($item->key !== null || $item->unpack || $item->byRef) {
                    throw new InspectionException('Line '.$node->getStartLine().': compact() requires literal names or unkeyed literal lists.');
                }
                array_push($names, ...$this->names($item->value));
            }

            return $names;
        }

        throw new InspectionException('Line '.$node->getStartLine().': Dynamic compact() variable names are not supported.');
    }
}
