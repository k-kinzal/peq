<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeKind;

/**
 * Indexed declarations and ancestry; all answers stay within the analysed graph.
 */
final class ClassHierarchy
{
    /**
     * @var array<string, Node>
     */
    private array $nodes = [];

    /**
     * @var array<string, Node>
     */
    private array $properties = [];

    /**
     * @var array<string, list<string>>
     */
    private array $parents = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $ancestors = [];

    /**
     * Indexes symbols and their declared ancestry without loading application classes.
     */
    public function __construct(Graph $graph)
    {
        foreach ($graph->nodes() as $node) {
            $this->nodes[strtolower($node->id()->toString())] = $node;
            if ($node->kind() === NodeKind::Property) {
                $owner = self::owner($node) ?? '';
                $this->properties[strtolower($owner).substr($node->id()->toString(), strlen($owner))] = $node;
            }
        }
        foreach ($graph->forwardEdges() as $edge) {
            if (in_array($edge->kind(), [EdgeKind::DeclarationExtends, EdgeKind::DeclarationImplements], true)) {
                $this->parents[strtolower($edge->from()->toString())][] = strtolower($edge->to()->toString());
            }
        }
    }

    /**
     * Finds the class-like owning a member, or null for a standalone symbol.
     */
    public static function owner(Node $node): ?string
    {
        $id = $node->id()->toString();
        $end = strpos($id, '::');

        return $end === false ? null : substr($id, 0, $end);
    }

    /**
     * Finds a declaration using PHP class and method name casing rules.
     */
    public function node(string $name): ?Node
    {
        return $this->nodes[strtolower($name)] ?? null;
    }

    /**
     * @return list<string>
     */
    public function ancestors(string $name): array
    {
        $key = strtolower($name);
        if (!isset($this->ancestors[$key])) {
            $seen = [];
            $pending = [$key];
            while ($pending !== []) {
                $next = array_shift($pending);
                if (isset($seen[$next])) {
                    continue;
                }
                $seen[$next] = true;
                array_push($pending, ...($this->parents[$next] ?? []));
            }
            $this->ancestors[$key] = $seen;
        }

        return array_keys($this->ancestors[$key]);
    }

    /**
     * Checks whether the recorded ancestry satisfies the requested type.
     */
    public function isSubtype(string $class, string $type): bool
    {
        return in_array(strtolower($type), $this->ancestors($class), true);
    }

    /**
     * Finds a member, preferring class declarations over interface contracts.
     */
    public function member(string $class, string $name, NodeKind $kind): ?Node
    {
        $ancestors = $this->ancestors($class);
        $interfaces = array_filter($ancestors, fn (string $owner): bool => $this->node($owner)?->kind() === NodeKind::Interface);
        $classes = array_filter($ancestors, fn (string $owner): bool => $this->node($owner)?->kind() !== NodeKind::Interface);
        $fallback = null;
        foreach ([...$classes, ...$interfaces] as $owner) {
            $node = $kind === NodeKind::Property ? ($this->properties[$owner.'::'.$name] ?? null) : $this->node($owner.'::'.$name);
            if ($node !== null && $node->kind() === $kind) {
                if ($node->resolved()) {
                    return $node;
                }
                $fallback ??= $node;
            }
        }

        return $fallback;
    }

    /**
     * Finds the method declaration selected by inheritance.
     */
    public function method(string $class, string $name): ?MethodNode
    {
        $node = $this->member($class, $name, NodeKind::Method);

        return $node instanceof MethodNode ? $node : null;
    }

    /**
     * @return list<Node>
     */
    public function concreteTypes(): array
    {
        return array_values(array_filter($this->nodes, static fn (Node $node): bool => in_array($node->kind(), [NodeKind::Klass, NodeKind::Enum], true)
            && $node->resolved() && !($node->declaration()?->modifiers->abstract ?? false)));
    }
}
