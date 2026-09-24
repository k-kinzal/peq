<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\Resolution\Issue;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\ExperimentAnalyzer\Structure\Inventory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

/**
 * Finds declared property sites while keeping object lifetime and aliasing unresolved.
 */
final class Properties
{
    /**
     * Locates a written class and property; inherited or dynamic properties are not guessed.
     *
     * @throws InspectionException If the class/property is absent or ambiguous
     */
    public function inspect(SourceIndex $index, string $target): DependencyGraph
    {
        [$class, $property] = explode('::$', ltrim($target, '\\'), 2);
        $found = null;
        foreach ($index->sources() as $source) {
            foreach ((new NodeFinder())->findInstanceOf($source->statements, ClassLike::class) as $node) {
                if ($node->namespacedName === null || strcasecmp($class, $node->namespacedName->toString()) !== 0) {
                    continue;
                }
                if ($found !== null) {
                    throw new InspectionException('Ambiguous class: '.$class.'. Analyze a narrower path.');
                }
                $text = file_get_contents($source->path);
                if ($text !== false) {
                    $found = $this->analyze($node, $property, new DependencyGraph($node->namespacedName->toString().'::$'.$property, $source->path, $text));
                }
            }
        }
        if ($found === null) {
            throw new InspectionException('Class not found: '.$class.'.');
        }

        return $found;
    }

    /**
     * Keeps each method's local dependencies without inventing a method execution order.
     *
     * @throws InspectionException If the property has no written declaration
     */
    public function analyze(ClassLike $class, string $property, DependencyGraph $graph): DependencyGraph
    {
        $declaration = $this->declaration($class, $property);
        if ($declaration === null) {
            throw new InspectionException('Declared property not found: '.$graph->target.'. Inherited and dynamic storage are not resolved.');
        }
        $graph->inventory = new Inventory();
        $graph->inventory->read($class, $graph);
        $declared = $graph->record($declaration, 'property-declaration', '$'.$property);
        $unknown = $graph->record($declaration, 'property-state');
        $graph->issues[$unknown] = new Issue('PROPERTY_LIFECYCLE', $unknown, 'property-sites/v1', 'Declaration and direct receiver sites are retained. Object identity, aliases, external writes, hooks and method invocation order are not solved; source order is not execution order.', $graph->nodes[$unknown], ['value', 'control', 'storage']);
        $graph->connect($declared, $unknown, 'unresolved-storage');
        $graph->connect($unknown, $declared, 'possible-definition');
        $graph->provenance = ['engine' => 'ExperimentAnalyzer', 'engineVersion' => 'structure-first/1', 'parserVersion' => \Composer\InstalledVersions::getPrettyVersion('nikic/php-parser'), 'rules' => 'checked-rules/v1', 'schemaVersion' => 2, 'runtimePhp' => PHP_VERSION, 'sourceSha256' => $graph->fingerprint()];
        foreach ($class->getMethods() as $method) {
            $local = (new Inspection())->analyze($method, $graph->emptyCopy());
            $graph->nodes += $local->nodes;
            $graph->edges += $local->edges;
            $graph->issues += $local->issues;
            $graph->scalars += $local->scalars;
        }
        $this->sites($class, $property, $unknown, $graph);

        return $graph;
    }

    /**
     * Includes promoted constructor parameters as property declarations.
     */
    public function declaration(ClassLike $class, string $property): ?Node
    {
        foreach ($class->getProperties() as $statement) {
            foreach ($statement->props as $item) {
                if ($item->name->toString() === $property) {
                    return $item;
                }
            }
        }
        foreach ($class->getMethod('__construct')->params ?? [] as $parameter) {
            if ($parameter->isPromoted() && $parameter->var instanceof Expr\Variable && $parameter->var->name === $property) {
                return $parameter->var;
            }
        }

        return null;
    }

    /**
     * Visits this class's body, excluding nested callable receiver scopes.
     */
    public function sites(Node $node, string $property, string $unknown, DependencyGraph $graph): void
    {
        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch || $node instanceof Expr\StaticPropertyFetch) {
            if ($this->matches($node, $property, $graph->target)) {
                $id = $graph->record($node, 'property-access', '$'.$property);
                $syntax = $graph->record($node, 'syntax-'.$node->getType());
                $graph->connect($id, $syntax, 'source-site');
                $graph->connect($id, $unknown, 'unresolved-storage');
                $graph->connect($unknown, $id, 'possible-access');
                $graph->connect($syntax, $id, 'source-site');
            }
        }
        foreach ($node->getSubNodeNames() as $name) {
            $children = get_object_vars($node)[$name] ?? null;
            foreach (is_array($children) ? $children : [$children] as $child) {
                if ($child instanceof Node && !$child instanceof ClassLike && !$child instanceof Node\Stmt\Function_ && !$child instanceof Expr\Closure && !$child instanceof Expr\ArrowFunction) {
                    $this->sites($child, $property, $unknown, $graph);
                }
            }
        }
    }

    /**
     * Accepts written $this, self and named-class receivers; other identities stay unknown.
     */
    public function matches(Expr\NullsafePropertyFetch|Expr\PropertyFetch|Expr\StaticPropertyFetch $node, string $property, string $target): bool
    {
        if (!$node->name instanceof Node\Identifier) {
            return false;
        }
        if ($node->name->toString() !== $property) {
            return false;
        }
        if ($node instanceof Expr\StaticPropertyFetch) {
            return $node->class instanceof Node\Name && in_array(strtolower($node->class->toString()), ['self', strtolower(explode('::', $target)[0])], true);
        }

        return $node->var instanceof Expr\Variable && $node->var->name === 'this';
    }
}
