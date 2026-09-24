<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Usage\PossibleCallEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Config\InspectFilter;

/**
 * Selects a report graph; the analysed graph remains available in full to GQL.
 */
final class InspectionGraph
{
    /**
     * Selects the graph that every reporter will read for this inspection.
     */
    public static function of(Graph $graph, Node $root, InspectFilter $filter, Direction $direction): Graph
    {
        return match ($filter) {
            InspectFilter::All => $graph,
            InspectFilter::Calls => self::calls($graph, $root, $direction),
            InspectFilter::Depend => self::dependencies($graph, $root, $direction),
        };
    }

    /**
     * Recognizes written calls and their possible implementation targets.
     */
    public static function isCall(Edge $edge): bool
    {
        return in_array($edge->kind(), [EdgeKind::MethodCall, EdgeKind::StaticCall, EdgeKind::FunctionCall, EdgeKind::PossibleCall], true);
    }

    /**
     * Selects call relations and the entry methods of a class-like root.
     */
    public static function calls(Graph $graph, Node $root, Direction $direction = Direction::Uses): Graph
    {
        $selected = new Graph();
        $selected->addNodes($graph->nodes());
        $hierarchy = new ClassHierarchy($graph);
        foreach ($graph->forwardEdges() as $edge) {
            if (self::isCall($edge)) {
                $selected->addEdge($edge);
            }
            if ($edge->kind() === EdgeKind::DeclarationMethod && $hierarchy->isSubtype($root->id()->toString(), $edge->from()->toString())) {
                $method = $graph->node($edge->to());
                $name = substr($edge->to()->toString(), strlen($edge->from()->toString()) + 2);
                if ($method === null || $hierarchy->method($root->id()->toString(), $name)?->id()->toString() !== $method->id()->toString()
                    || ($edge->from()->toString() !== $root->id()->toString() && $method->declaration()?->visibility === Visibility::Private)
                ) {
                    continue;
                }
                $selected->addEdge($direction === Direction::Uses
                    ? new ProjectedRelation($root, $method, $edge)
                    : new ProjectedRelation($method, $root, $edge));
            }
        }

        return $selected;
    }

    /**
     * @return array<string, true>
     */
    public static function callableScope(Graph $graph, Node $root, Direction $direction): array
    {
        $scope = [];
        $pending = [$root->id()];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($scope[$id->toString()])) {
                continue;
            }
            $scope[$id->toString()] = true;
            foreach ($graph->edges($id) as $edge) {
                $original = $edge->kind()->direction() === Direction::UsedBy ? $edge->invert() : $edge;
                if ($edge->kind()->direction() === $direction && self::isCall($original)) {
                    $pending[] = $edge->to();
                }
            }
        }

        return $scope;
    }

    /**
     * Aggregates dependencies onto owners, retaining a callable root as its scope.
     */
    public static function dependencies(Graph $graph, Node $root, Direction $direction): Graph
    {
        $selected = new Graph();
        $selected->addNode($root);
        $callable = in_array($root->kind(), [NodeKind::Method, NodeKind::Function], true);
        $scope = $callable ? self::callableScope($graph, $root, $direction) : null;
        foreach ($graph->forwardEdges() as $edge) {
            if (self::isContainment($edge) || ($scope !== null && !isset($scope[($direction === Direction::Uses ? $edge->from() : $edge->to())->toString()]))) {
                continue;
            }
            self::project($graph, $selected, $edge, $root, $callable);
            if ($edge->kind() === EdgeKind::PropertyAccess || $edge->kind() === EdgeKind::StaticPropertyAccess) {
                foreach ($graph->edges($edge->to()) as $type) {
                    if ($type->kind() === EdgeKind::DeclarationTypeProperty) {
                        self::project($graph, $selected, $type, $root, $callable);
                    }
                }
            }
        }

        return $selected;
    }

    /**
     * Recognizes membership declarations that do not themselves express a dependency.
     */
    public static function isContainment(Edge $edge): bool
    {
        return in_array($edge->kind(), [EdgeKind::DeclarationMethod, EdgeKind::DeclarationProperty, EdgeKind::DeclarationConstant, EdgeKind::DeclarationEnumCase], true);
    }

    /**
     * Carries one relation into the graph of owning symbols.
     */
    public static function project(Graph $graph, Graph $selected, Edge $edge, Node $root, bool $callable): void
    {
        $from = self::owner($graph, $graph->node($edge->from()), $root, $callable);
        $to = self::owner($graph, $graph->node($edge->to()), $root, $callable);
        if ($from === null || $to === null || $from->id()->toString() === $to->id()->toString()) {
            return;
        }
        $selected->addNodes([$from, $to]);
        $selected->addEdge(new ProjectedRelation($from, $to, $edge));
        $implementation = $edge instanceof PossibleCallEdge && $edge->implementationType !== null ? $graph->nodeNamed($edge->implementationType) : null;
        if ($implementation !== null && $implementation->id()->toString() !== $from->id()->toString()) {
            $selected->addNode($implementation);
            $selected->addEdge(new ProjectedRelation($from, $implementation, $edge));
        }
    }

    /**
     * Finds the symbol that represents an endpoint in the dependency report.
     */
    public static function owner(Graph $graph, ?Node $node, Node $root, bool $callable): ?Node
    {
        if ($node === null || $node->kind() === NodeKind::Builtin || str_starts_with($node->id()->toString(), 'unresolved-call@')) {
            return null;
        }
        $owner = ClassHierarchy::owner($node);
        if ($callable && ($node->id()->toString() === $root->id()->toString() || ($owner !== null && $owner === ClassHierarchy::owner($root)))) {
            return $root;
        }
        if ($owner !== null) {
            return $graph->nodeNamed($owner) ?? new ClassNode(ClassNodeId::of($owner));
        }

        return $node;
    }
}
