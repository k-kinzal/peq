<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Structure;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Records all syntax before solving, including deferred and unreachable bodies.
 * Parent/role links are lexical containment, never execution predicates.
 */
final class Inventory
{
    /**
     * @var array<string, Site>
     */
    public array $sites = [];

    /**
     * Visits the complete syntax tree without following control flow.
     */
    public function read(Node $node, DependencyGraph $graph, ?string $parent = null, string $role = 'callable'): void
    {
        $variable = $node instanceof Expr\Variable && is_string($node->name) ? '$'.$node->name : null;
        $source = $graph->location($node, 'syntax-'.$node->getType(), $variable);
        if (isset($this->sites[$source->id])) {
            $source = $graph->location($node, 'syntax-'.$node->getType().'-'.count($this->sites), $variable);
        }
        $this->sites[$source->id] = new Site($source, $node->getType(), $parent, $role, $this->target($node), $node->getStartFilePos(), $node->getEndFilePos());
        foreach ($node->getSubNodeNames() as $name) {
            $children = get_object_vars($node)[$name] ?? null;
            foreach (is_array($children) ? $children : [$children] as $key => $child) {
                if ($child instanceof Node) {
                    $this->read($child, $graph, $source->id, $name.(is_array($children) ? '['.$key.']' : ''));
                }
            }
        }
    }

    /**
     * Returns only a written target; a dynamic receiver is not a resolved class.
     */
    public function target(Node $node): ?string
    {
        if ($node instanceof Expr\New_ || $node instanceof Expr\StaticCall) {
            return $node->class instanceof Node\Name ? $node->class->toString().($node instanceof Expr\StaticCall && $node->name instanceof Node\Identifier ? '::'.$node->name->toString() : '') : null;
        }
        if ($node instanceof Expr\FuncCall) {
            return $node->name instanceof Node\Name ? $node->name->toString() : null;
        }

        return null;
    }
}
