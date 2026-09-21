<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\Flow\Expressions;
use App\Analyzer\ExperimentAnalyzer\Flow\Recording;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use App\Analyzer\ExperimentAnalyzer\Flow\Statements;
use App\Analyzer\ExperimentAnalyzer\Invocation\CallEffects;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;

/**
 * Locates a written callable without importing the analysed program into PHP.
 */
final class Inspection
{
    /**
     * Locates the requested written callable and analyzes its local flow.
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function inspect(SourceIndex $index, string $target): DependencyGraph
    {
        $signatures = [];
        foreach ($index->sources() as $source) {
            foreach ($this->callables($source->statements) as $name => $callable) {
                foreach ($callable->params as $position => $parameter) {
                    $signatures[strtolower($name)][$position] = $parameter->byRef;
                    if ($parameter->variadic) {
                        $signatures[strtolower($name)]['...'] = $parameter->byRef;
                    }
                    if ($parameter->var instanceof Variable && is_string($parameter->var->name)) {
                        $signatures[strtolower($name)][$parameter->var->name] = $parameter->byRef;
                    }
                }
                $signatures[strtolower($name)] ??= [];
            }
        }
        $found = null;
        foreach ($index->sources() as $source) {
            foreach ($this->callables($source->statements) as $name => $callable) {
                if (strcasecmp(ltrim($target, '\\'), $name) === 0) {
                    if ($found !== null) {
                        throw new InspectionException('Ambiguous callable: '.$target.'. Analyze a narrower path.');
                    }
                    $text = file_get_contents($source->path);
                    if ($text !== false) {
                        $found = $this->analyze($callable, new DependencyGraph($name, $source->path, $text), new CallEffects($signatures, explode('::', $name)[0]));
                    }
                }
            }
        }
        if ($found === null) {
            throw new InspectionException('Callable not found: '.$target.'. Select a method or function declared in a readable, valid PHP source file.');
        }

        return $found;
    }

    /**
     * @param list<Node\Stmt> $statements
     *
     * @return array<string, ClassMethod|Function_>
     */
    public function callables(array $statements): array
    {
        $callables = [];
        foreach ((new NodeFinder())->find($statements, static fn (Node $node): bool => $node instanceof ClassLike || $node instanceof Function_) as $node) {
            if ($node instanceof ClassLike && $node->namespacedName !== null) {
                foreach ($node->getMethods() as $method) {
                    if ($method->stmts !== null) {
                        $callables[$node->namespacedName->toString().'::'.$method->name->toString()] = $method;
                    }
                }
            } elseif ($node instanceof Function_ && $node->namespacedName !== null) {
                $callables[$node->namespacedName->toString()] = $node;
            }
        }

        return $callables;
    }

    /**
     * Builds the local dependency graph of one callable.
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function analyze(ClassMethod|Function_ $callable, DependencyGraph $graph, CallEffects $calls = new CallEffects()): DependencyGraph
    {
        $body = $callable->stmts ?? [];
        (new SupportedSyntax())->check($body);
        $recording = new Recording($graph);
        $state = new State();
        foreach ((new NodeFinder())->findInstanceOf($body, Variable::class) as $variable) {
            if (is_string($variable->name)) {
                $name = '$'.$variable->name;
                if (!isset($state->definitions[$name])) {
                    $id = $graph->record($variable, $name === '$this' ? 'receiver' : 'unbound', $name);
                    $state->definitions[$name] = [$id => true];
                }
            }
        }
        foreach ($callable->params as $parameter) {
            if ($parameter->var instanceof Variable) {
                $recording->write($parameter->var, [], $state, 'parameter');
            }
        }
        (new Statements(new Expressions($recording, $calls)))->read($body, $state);

        return $graph;
    }
}
