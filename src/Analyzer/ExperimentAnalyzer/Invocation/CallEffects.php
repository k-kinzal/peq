<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Invocation;

use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\Flow\Assignments;
use App\Analyzer\ExperimentAnalyzer\Flow\Expressions;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node;
use PhpParser\Node\Expr;
use ReflectionException;
use ReflectionFunction;

/**
 * By-reference parameters define a new boundary value after a call returns.
 */
final readonly class CallEffects
{
    /**
     * Builtin output-only parameters do not read the previous variable value.
     *
     * @var array<string, array{int, string}>
     */
    public const array OUTPUTS = [
        'preg_match' => [2, 'matches'],
        'preg_match_all' => [2, 'matches'],
        'parse_str' => [1, 'result'],
        'mb_parse_str' => [1, 'result'],
    ];

    /**
     * @param array<string, array<int|string, bool>> $signatures Written callable signatures
     */
    public function __construct(private array $signatures = [], private string $className = '') {}

    /**
     * Reads call inputs while excluding plain variables used only as builtin outputs.
     *
     * @return list<string>
     */
    public function inputs(Expr\FuncCall|Expr\MethodCall|Expr\New_|Expr\StaticCall $node, Expressions $expressions, State $state): array
    {
        $name = strtolower($this->name($node) ?? '');
        $output = self::OUTPUTS[$name] ?? null;
        if (!$node instanceof Expr\FuncCall || $output === null || isset($this->signatures[$name]) || $node->isFirstClassCallable()) {
            return $expressions->children($node, $state);
        }
        $input = clone $node;
        $input->args = [];
        foreach ($node->getArgs() as $position => $argument) {
            $key = $argument->name?->toString() ?? $position;
            if (!in_array($key, $output, true) || !$argument->value instanceof Expr\Variable || $argument->unpack) {
                $input->args[] = $argument;
            }
        }

        return $expressions->children($input, $state);
    }

    /**
     * Applies known reference parameter effects without executing the callee.
     */
    public function apply(Expr\FuncCall|Expr\MethodCall|Expr\New_|Expr\StaticCall $node, string $call, Expressions $expressions, State $state): void
    {
        if ($node->isFirstClassCallable()) {
            return;
        }
        $signature = $this->signature($node);
        foreach ($node->getArgs() as $position => $argument) {
            if (($signature[$argument->name?->toString() ?? $position] ?? $signature['...'] ?? false) && !$argument->unpack) {
                if ($argument->value instanceof Expr\Variable) {
                    $expressions->recording->write($argument->value, [$call], $state, 'call-write');
                } else {
                    (new Assignments($expressions))->write($argument->value, [$call], $state);
                }
            }
        }
    }

    /**
     * Resolves written signatures first and reflects only already installed PHP builtins.
     *
     * @return array<int|string, bool>
     *
     * @throws InspectionException If a builtin signature cannot be read
     */
    public function signature(Expr\FuncCall|Expr\MethodCall|Expr\New_|Expr\StaticCall $node): array
    {
        $name = $this->name($node);
        if ($name === null) {
            return [];
        }
        $name = strtolower($name);
        if (isset($this->signatures[$name])) {
            return $this->signatures[$name];
        }
        if (!$node instanceof Expr\FuncCall || !function_exists($name)) {
            return [];
        }

        try {
            $function = new ReflectionFunction($name);
        } catch (ReflectionException $error) {
            throw new InspectionException('Cannot read the builtin signature of '.$name, previous: $error);
        }
        if (!$function->isInternal()) {
            return [];
        }
        $signature = [];
        foreach ($function->getParameters() as $position => $parameter) {
            $signature[$position] = $parameter->isPassedByReference();
            $signature[$parameter->getName()] = $parameter->isPassedByReference();
            if ($parameter->isVariadic()) {
                $signature['...'] = $parameter->isPassedByReference();
            }
        }

        return $signature;
    }

    /**
     * Names calls whose signature is known without receiver type inference.
     */
    public function name(Expr\FuncCall|Expr\MethodCall|Expr\New_|Expr\StaticCall $node): ?string
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $qualified = $node->name->getAttribute('namespacedName');
            if ($qualified instanceof Node\Name && isset($this->signatures[strtolower($qualified->toString())])) {
                return $qualified->toString();
            }

            return $node->name->toString();
        }
        if ($node instanceof Expr\New_ && $node->class instanceof Node\Name) {
            $class = $node->class->isSpecialClassName() ? $this->className : $node->class->toString();

            return strtolower($node->class->toString()) === 'parent' ? null : $class.'::__construct';
        }
        if ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
            $class = $node->class->isSpecialClassName() ? $this->className : $node->class->toString();

            return strtolower($node->class->toString()) === 'parent' ? null : $class.'::'.$node->name->toString();
        }
        if ($node instanceof Expr\MethodCall && $node->var instanceof Expr\Variable && $node->var->name === 'this' && $node->name instanceof Node\Identifier) {
            return $this->className.'::'.$node->name->toString();
        }

        return null;
    }
}
