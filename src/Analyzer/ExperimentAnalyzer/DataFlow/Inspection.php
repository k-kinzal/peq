<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\Flow\Expressions;
use App\Analyzer\ExperimentAnalyzer\Flow\Recording;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use App\Analyzer\ExperimentAnalyzer\Flow\Statements;
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
        if (str_contains($target, '::$')) {
            return (new Properties())->inspect($index, $target);
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
                        $found = $this->analyze($callable, new DependencyGraph($name, $source->path, $text));
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
     * Builds the local dependency graph of one callable, retaining unsolved regions.
     */
    public function analyze(ClassMethod|Function_ $callable, DependencyGraph $graph): DependencyGraph
    {
        $body = $callable->stmts ?? [];
        $graph->inventory = new \App\Analyzer\ExperimentAnalyzer\Structure\Inventory();
        $graph->inventory->read($callable, $graph);
        $graph->provenance = ['engine' => 'ExperimentAnalyzer', 'engineVersion' => 'structure-first/1', 'parserVersion' => \Composer\InstalledVersions::getPrettyVersion('nikic/php-parser'), 'rules' => 'checked-rules/v1', 'schemaVersion' => 2, 'runtimePhp' => PHP_VERSION, 'sourceSha256' => $graph->fingerprint()];
        $recording = new Recording($graph);
        $state = new State();
        foreach ((new NodeFinder())->findInstanceOf($body, Variable::class) as $variable) {
            if (is_string($variable->name)) {
                $name = '$'.$variable->name;
                if (!isset($state->definitions[$name])) {
                    $receiver = $name === '$this' && $callable instanceof ClassMethod && !$callable->isStatic();
                    $id = $graph->record($variable, $receiver ? 'receiver' : 'unbound', $name);
                    $state->definitions[$name] = [$id => true];
                }
            }
        }
        foreach ($callable->params as $parameter) {
            if ($parameter->var instanceof Variable) {
                $id = $recording->write($parameter->var, [], $state, 'parameter');
                $graph->scalars[$id] = !$parameter->variadic && \App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins::parameter($parameter->type);
            }
        }
        $unstructured = (new NodeFinder())->findFirst($body, static fn (Node $node): bool => $node instanceof Node\Stmt\Goto_ || $node instanceof Node\Stmt\Label);
        $aliases = array_filter($callable->params, static fn (Node\Param $parameter): bool => $parameter->byRef);
        if ($unstructured !== null || $aliases !== []) {
            (new \App\Analyzer\ExperimentAnalyzer\Resolution\Boundary($graph))->read($callable, $state, 'NONLOCAL_FLOW', 'Jump targets or aliased parameters require a whole-callable model.');
        } else {
            (new Statements(new Expressions($recording)))->read($body, $state)->validate($graph);
        }

        return $graph;
    }
}
