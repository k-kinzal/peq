<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\InverseEdge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * The graph model read back from the source tree that declares it.
 *
 * Some contracts are about the model as a whole rather than about any one type:
 * that every kind of relation has a class, that every kind of symbol has a node and
 * an identifier, that nothing was added to one side without the other. Answering
 * those means looking at the declarations themselves, which is reflection — so it
 * happens here, and the contract tests assert over the plain lists it returns.
 */
final class GraphModel
{
    /**
     * Returns every class in the graph model that stands for a relation.
     *
     * @return list<class-string<Edge>> The edge classes, in file name order
     */
    public static function edgeClasses(): array
    {
        return self::classesIn('Edge', 'App\Analyzer\Graph\Edge\\', Edge::class);
    }

    /**
     * Returns every class in the graph model that stands for a symbol.
     *
     * @return list<class-string<Node>> The node classes, in file name order
     */
    public static function nodeClasses(): array
    {
        return self::classesIn('Node', 'App\Analyzer\Graph\Node\\', Node::class);
    }

    /**
     * Returns every class in the graph model that identifies a symbol.
     *
     * @return list<class-string<NodeId<Node>>> The identifier classes, in file name order
     */
    public static function nodeIdClasses(): array
    {
        return self::classesIn('NodeId', 'App\Analyzer\Graph\NodeId\\', NodeId::class);
    }

    /**
     * Returns the classes that stand for a relation source code actually writes.
     *
     * @return list<class-string<Edge>> The authored edge classes
     */
    public static function authoredEdgeClasses(): array
    {
        return array_values(array_filter(
            self::edgeClasses(),
            static fn (string $class): bool => !is_subclass_of($class, InverseEdge::class),
        ));
    }

    /**
     * Returns the classes that stand for the opposite reading of a relation.
     *
     * @return list<class-string<Edge>> The inverse edge classes
     */
    public static function inverseEdgeClasses(): array
    {
        return array_values(array_filter(
            self::edgeClasses(),
            static fn (string $class): bool => is_subclass_of($class, InverseEdge::class),
        ));
    }

    /**
     * Returns the kinds of relation the model declares.
     *
     * @return list<EdgeKind> Every edge kind
     */
    public static function edgeKinds(): array
    {
        return EdgeKind::cases();
    }

    /**
     * Returns the kinds of symbol the model declares.
     *
     * @return list<NodeKind> Every node kind
     */
    public static function nodeKinds(): array
    {
        return NodeKind::cases();
    }

    /**
     * Reads the classes of one directory of the graph model.
     *
     * @template TContract of object
     *
     * @param string                  $directory The directory below src/Analyzer/Graph
     * @param string                  $namespace The namespace those files declare into
     * @param class-string<TContract> $contract  The contract every one of them must satisfy
     *
     * @return list<class-string<TContract>> The classes found, in file name order
     */
    public static function classesIn(string $directory, string $namespace, string $contract): array
    {
        $files = glob(dirname(__DIR__, 3).'/src/Analyzer/Graph/'.$directory.'/*.php');
        $classes = [];

        foreach ($files === false ? [] : $files as $file) {
            $class = $namespace.basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, $contract)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
