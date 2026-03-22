<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\Graph\Edge\ConstFetchEdge;
use App\Analyzer\Graph\Edge\DeclarationConstantEdge;
use App\Analyzer\Graph\Edge\DeclarationEnumCaseEdge;
use App\Analyzer\Graph\Edge\DeclarationExtendsEdge;
use App\Analyzer\Graph\Edge\DeclarationImplementsEdge;
use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTraitUseEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeParameterEdge;
use App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge;
use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\Edge\InstantiationEdge;
use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\Edge\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\Edge\StaticPropertyAccessEdge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;
use Faker\Provider\Base;

/**
 * Faker provider for generating complete dependency graphs.
 *
 * This provider creates fully-formed Graph objects with randomly generated
 * nodes and edges using a recursive, hierarchical approach that mimics
 * real PHP code structure.
 *
 * @property GraphGenerator $generator
 */
final class GraphProvider extends Base
{
    /**
     * Generates a complete dependency graph with nodes and edges.
     *
     * Creates a graph starting from a random root node type (Class, Interface, Trait, or Function)
     * and recursively generates related nodes and edges to form a complete dependency structure.
     *
     * @param int $depth Maximum recursion depth (default: 5)
     *
     * @return Graph A complete dependency graph with nodes and edges
     */
    public function graph(int $depth = 5): Graph
    {
        $rootKinds = [NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Function];

        /** @var NodeKind $rootKind */
        $rootKind = $this->generator->randomElement($rootKinds);

        return match ($rootKind) {
            NodeKind::Klass => $this->classGraph(null, $depth - 1),
            NodeKind::Interface => $this->interfaceGraph(null, $depth - 1),
            NodeKind::Trait => $this->traitGraph(null, $depth - 1),
            NodeKind::Function => $this->functionGraph(null, $depth - 1),
            default => throw new \LogicException('Unsupported root node kind.'),
        };
    }

    public function typeGraph(
        null|BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $nodeId = null,
        int $depth = 5
    ): Graph {
        if ($nodeId === null) {
            /** @var BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $nodeId */
            $nodeId = $this->generator->randomElement([
                $this->generator->classNodeId(),
                $this->generator->interfaceNodeId(),
                $this->generator->enumNodeId(),
                $this->generator->builtinNodeId(),
            ]);
        }

        return match (true) {
            $nodeId instanceof ClassNodeId => $this->classGraph($nodeId, $depth),
            $nodeId instanceof InterfaceNodeId => $this->interfaceGraph($nodeId, $depth),
            $nodeId instanceof EnumNodeId => $this->enumGraph($nodeId, $depth),
            $nodeId instanceof BuiltinNodeId => $this->builtinGraph($nodeId),
        };
    }

    /**
     * Generates a class graph with all its members and relationships.
     *
     * @param null|ClassNodeId $symbol Optional class NodeId to use as root
     * @param int              $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph the class graph with methods, properties, constants, inheritance, etc
     */
    private function classGraph(?ClassNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $classId = $symbol ?? $this->generator->classNodeId();
        $class = new ClassNode(
            id: $classId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
        $graph->addNode($class);

        if ($depth === 0) {
            return $graph;
        }

        $methodIds = $this->generator->array(fn () => $this->generator->methodNodeId(), 0, 5);
        foreach ($methodIds as $methodId) {
            $methodGraph = $this->methodGraph($methodId, $depth - 1);
            $graph = $graph->merge($methodGraph);
            $method = $graph->node($methodId);
            assert($method instanceof MethodNode);
            $graph->addEdge(new DeclarationMethodEdge($class, $method, $this->generator->fileMeta()));
        }

        $propertyIds = $this->generator->array(fn () => $this->generator->propertyNodeId(), 0, 5);
        foreach ($propertyIds as $propertyId) {
            $propertyGraph = $this->propertyGraph($propertyId, $depth - 1);
            $graph = $graph->merge($propertyGraph);
            $property = $graph->node($propertyId);
            assert($property instanceof PropertyNode);
            $graph->addEdge(new DeclarationPropertyEdge($class, $property, $this->generator->fileMeta()));
        }

        $constantIds = $this->generator->array(fn () => $this->generator->constantNodeId(), 0, 5);
        foreach ($constantIds as $constantId) {
            $constantGraph = $this->constantGraph($constantId);
            $graph = $graph->merge($constantGraph);
            $constant = $graph->node($constantId);
            assert($constant instanceof ConstantNode);
            $graph->addEdge(new DeclarationConstantEdge($class, $constant, $this->generator->fileMeta()));
        }

        /** @var null|ClassNodeId $parentId */
        // @phpstan-ignore-next-line
        $parentId = $this->generator->optional()->classNodeId();
        if ($parentId !== null) {
            $parentGraph = $this->classGraph($parentId, $depth - 1);
            $graph = $graph->merge($parentGraph);
            $parent = $graph->node($parentId);
            assert($parent instanceof ClassNode);
            $graph->addEdge(new DeclarationExtendsEdge($class, $parent, $this->generator->fileMeta()));
        }

        $interfaceIds = $this->generator->array(fn () => $this->generator->interfaceNodeId(), 0, 5);
        foreach ($interfaceIds as $interfaceId) {
            $interfaceGraph = $this->interfaceGraph($interfaceId, $depth - 1);
            $graph = $graph->merge($interfaceGraph);
            $interface = $graph->node($interfaceId);
            assert($interface instanceof GraphInterfaceNode);
            $graph->addEdge(new DeclarationImplementsEdge($class, $interface, $this->generator->fileMeta()));
        }

        $traitIds = $this->generator->array(fn () => $this->generator->traitNodeId(), 0, 5);
        foreach ($traitIds as $traitId) {
            $traitGraph = $this->traitGraph($traitId, $depth - 1);
            $graph = $graph->merge($traitGraph);
            $trait = $graph->node($traitId);
            assert($trait instanceof TraitNode);
            $graph->addEdge(new DeclarationTraitUseEdge($class, $trait, $this->generator->fileMeta()));
        }

        return $graph;
    }

    /**
     * Generates an interface graph with its methods and constants.
     *
     * @param null|InterfaceNodeId $symbol Optional interface NodeId to use as root
     * @param int                  $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph The interface graph
     */
    private function interfaceGraph(?InterfaceNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $interfaceId = $symbol ?? $this->generator->interfaceNodeId();
        $interface = new GraphInterfaceNode(
            id: $interfaceId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($interface);

        if ($depth === 0) {
            return $graph;
        }

        $methodIds = $this->generator->array(fn () => $this->generator->methodNodeId(), 0, 5);
        foreach ($methodIds as $methodId) {
            $methodGraph = $this->methodGraph($methodId, $depth - 1);
            $graph = $graph->merge($methodGraph);
            $method = $graph->node($methodId);
            assert($method instanceof MethodNode);
            $graph->addEdge(new DeclarationMethodEdge($interface, $method, $this->generator->fileMeta()));
        }

        $constantIds = $this->generator->array(fn () => $this->generator->constantNodeId(), 0, 5);
        foreach ($constantIds as $constantId) {
            $constantGraph = $this->constantGraph($constantId);
            $graph = $graph->merge($constantGraph);
            $constant = $graph->node($constantId);
            assert($constant instanceof ConstantNode);
            $graph->addEdge(new DeclarationConstantEdge($interface, $constant, $this->generator->fileMeta()));
        }

        /** @var null|InterfaceNodeId $parentId */
        // @phpstan-ignore-next-line
        $parentId = $this->generator->optional()->interfaceNodeId();
        if ($parentId !== null) {
            $parentGraph = $this->interfaceGraph($parentId, $depth - 1);
            $graph = $graph->merge($parentGraph);
            $parent = $graph->node($parentId);
            assert($parent instanceof GraphInterfaceNode);
            $graph->addEdge(new DeclarationExtendsEdge($interface, $parent, $this->generator->fileMeta()));
        }

        return $graph;
    }

    /**
     * Generates a trait graph with its methods and properties.
     *
     * @param null|TraitNodeId $symbol Optional trait NodeId to use as root
     * @param int              $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph The trait graph
     */
    private function traitGraph(?TraitNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $traitId = $symbol ?? $this->generator->traitNodeId();
        $trait = new TraitNode(
            id: $traitId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($trait);

        if ($depth === 0) {
            return $graph;
        }

        $methodIds = $this->generator->array(fn () => $this->generator->methodNodeId(), 0, 5);
        foreach ($methodIds as $methodId) {
            $methodGraph = $this->methodGraph($methodId, $depth - 1);
            $graph = $graph->merge($methodGraph);
            $method = $graph->node($methodId);
            assert($method instanceof MethodNode);
            $graph->addEdge(new DeclarationMethodEdge($trait, $method, $this->generator->fileMeta()));
        }

        $propertyIds = $this->generator->array(fn () => $this->generator->propertyNodeId(), 0, 5);
        foreach ($propertyIds as $propertyId) {
            $propertyGraph = $this->propertyGraph($propertyId, $depth - 1);
            $graph = $graph->merge($propertyGraph);
            $property = $graph->node($propertyId);
            assert($property instanceof PropertyNode);
            $graph->addEdge(new DeclarationPropertyEdge($trait, $property, $this->generator->fileMeta()));
        }

        return $graph;
    }

    /**
     * Generates a method graph with parameters, return type, and method body dependencies.
     *
     * @param null|MethodNodeId $symbol Optional method NodeId to use as root
     * @param int               $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph The method graph
     */
    private function methodGraph(?MethodNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $methodId = $symbol ?? $this->generator->methodNodeId();
        $method = new MethodNode(
            id: $methodId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($method);

        if ($depth === 0) {
            return $graph;
        }

        $returnTypeId = $this->generator->typeNodeId();
        $returnTypeGraph = $this->typeGraph($returnTypeId, $depth - 1);
        $graph = $graph->merge($returnTypeGraph);

        /** @var BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode $returnType */
        $returnType = $graph->node($returnTypeId);
        $edge = new DeclarationTypeReturnEdge(
            from: $method,
            to: $returnType,
            meta: $this->generator->fileMeta()
        );
        $graph->addEdge($edge);

        $paramTypeIds = $this->generator->array(fn () => $this->generator->typeNodeId(), 0, 5);

        foreach ($paramTypeIds as $paramTypeId) {
            $paramTypeGraph = $this->typeGraph($paramTypeId, $depth - 1);

            $graph = $graph->merge($paramTypeGraph);
            $paramType = $graph->node($paramTypeId);
            assert(
                $paramType instanceof ClassNode
                || $paramType instanceof GraphInterfaceNode
                || $paramType instanceof EnumNode
                || $paramType instanceof TraitNode
                || $paramType instanceof BuiltinNode
            );
            $graph->addEdge(new DeclarationTypeParameterEdge(
                $method,
                $paramType,
                $this->generator->fileMeta()
            ));
        }

        /** @var null|MethodNodeId $calledMethodId */
        // @phpstan-ignore-next-line
        $calledMethodId = $this->generator->optional()->methodNodeId();
        if ($calledMethodId !== null) {
            $calledGraph = $this->methodGraph($calledMethodId, $depth - 1);
            $graph = $graph->merge($calledGraph);
            $calledMethod = $graph->node($calledMethodId);
            assert($calledMethod instanceof MethodNode);
            $graph->addEdge(new MethodCallEdge($method, $calledMethod, $this->generator->fileMeta()));
        }

        /** @var null|MethodNodeId $staticMethodId */
        // @phpstan-ignore-next-line
        $staticMethodId = $this->generator->optional()->methodNodeId();
        if ($staticMethodId !== null) {
            $staticGraph = $this->methodGraph($staticMethodId, $depth - 1);
            $graph = $graph->merge($staticGraph);
            $staticMethod = $graph->node($staticMethodId);
            assert($staticMethod instanceof MethodNode);
            $graph->addEdge(new StaticCallEdge($method, $staticMethod, $this->generator->fileMeta()));
        }

        /** @var null|FunctionNodeId $funcId */
        // @phpstan-ignore-next-line
        $funcId = $this->generator->optional()->functionNodeId();
        if ($funcId !== null) {
            $funcGraph = $this->functionGraph($funcId, $depth - 1);
            $graph = $graph->merge($funcGraph);
            $func = $graph->node($funcId);
            assert($func instanceof FunctionNode);
            $graph->addEdge(new FunctionCallEdge($method, $func, $this->generator->fileMeta()));
        }

        /** @var null|PropertyNodeId $propId */
        // @phpstan-ignore-next-line
        $propId = $this->generator->optional()->propertyNodeId();
        if ($propId !== null) {
            $propGraph = $this->propertyGraph($propId, $depth - 1);
            $graph = $graph->merge($propGraph);
            $prop = $graph->node($propId);
            assert($prop instanceof PropertyNode);
            $graph->addEdge(new PropertyAccessEdge($method, $prop, $this->generator->fileMeta()));
        }

        /** @var null|PropertyNodeId $staticPropId */
        // @phpstan-ignore-next-line
        $staticPropId = $this->generator->optional()->propertyNodeId();
        if ($staticPropId !== null) {
            $staticPropGraph = $this->propertyGraph($staticPropId, $depth - 1);
            $graph = $graph->merge($staticPropGraph);
            $staticProp = $graph->node($staticPropId);
            assert($staticProp instanceof PropertyNode);
            $graph->addEdge(new StaticPropertyAccessEdge($method, $staticProp, $this->generator->fileMeta()));
        }

        /** @var null|ConstantNodeId $constId */
        // @phpstan-ignore-next-line
        $constId = $this->generator->optional()->constantNodeId();
        if ($constId !== null) {
            $constGraph = $this->constantGraph($constId);
            $graph = $graph->merge($constGraph);
            $const = $graph->node($constId);
            assert($const instanceof ConstantNode);
            $graph->addEdge(new ConstFetchEdge($method, $const, $this->generator->fileMeta()));
        }

        /** @var null|ClassNodeId $instantiatedId */
        // @phpstan-ignore-next-line
        $instantiatedId = $this->generator->optional()->classNodeId();
        if ($instantiatedId !== null) {
            $instantiatedGraph = $this->classGraph($instantiatedId, $depth - 1);
            $graph = $graph->merge($instantiatedGraph);
            $instantiated = $graph->node($instantiatedId);
            assert($instantiated instanceof ClassNode);
            $graph->addEdge(new InstantiationEdge($method, $instantiated, $this->generator->fileMeta()));
        }

        return $graph;
    }

    /**
     * Generates a function graph with parameters, return type, and function body dependencies.
     *
     * @param null|FunctionNodeId $symbol Optional function NodeId to use as root
     * @param int                 $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph The function graph
     */
    private function functionGraph(?FunctionNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $funcId = $symbol ?? $this->generator->functionNodeId();
        $func = new FunctionNode(
            id: $funcId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($func);

        if ($depth === 0) {
            return $graph;
        }

        $returnTypeId = $this->generator->typeNodeId();
        $returnTypeGraph = $this->typeGraph($returnTypeId, $depth - 1);

        $graph = $graph->merge($returnTypeGraph);
        $returnType = $graph->node($returnTypeId);
        assert(
            $returnType instanceof ClassNode
            || $returnType instanceof GraphInterfaceNode
            || $returnType instanceof EnumNode
            || $returnType instanceof TraitNode
            || $returnType instanceof BuiltinNode
        );
        $graph->addEdge(new DeclarationTypeReturnEdge(
            $func,
            $returnType,
            $this->generator->fileMeta()
        ));

        $paramTypeIds = $this->generator->array(fn () => $this->generator->typeNodeId(), 0, 5);

        foreach ($paramTypeIds as $paramTypeId) {
            $paramTypeGraph = $this->typeGraph($paramTypeId, $depth - 1);

            $graph = $graph->merge($paramTypeGraph);
            $paramType = $graph->node($paramTypeId);
            assert(
                $paramType instanceof ClassNode
                || $paramType instanceof GraphInterfaceNode
                || $paramType instanceof EnumNode
                || $paramType instanceof TraitNode
                || $paramType instanceof BuiltinNode
            );
            $graph->addEdge(new DeclarationTypeParameterEdge(
                $func,
                $paramType,
                $this->generator->fileMeta()
            ));
        }

        /** @var null|FunctionNodeId $calledFuncId */
        // @phpstan-ignore-next-line
        $calledFuncId = $this->generator->optional()->functionNodeId();
        if ($calledFuncId !== null) {
            $calledGraph = $this->functionGraph($calledFuncId, $depth - 1);
            $graph = $graph->merge($calledGraph);
            $calledFunc = $graph->node($calledFuncId);
            assert($calledFunc instanceof FunctionNode);
            $graph->addEdge(new FunctionCallEdge($func, $calledFunc, $this->generator->fileMeta()));
        }

        return $graph;
    }

    /**
     * Generates a property graph with its type declaration.
     *
     * @param null|PropertyNodeId $symbol Optional property NodeId to use as root
     * @param int                 $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph The property graph
     */
    private function propertyGraph(?PropertyNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $propertyId = $symbol ?? $this->generator->propertyNodeId();
        $property = new PropertyNode(
            id: $propertyId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($property);

        if ($depth === 0) {
            return $graph;
        }

        $propertyTypeId = $this->generator->typeNodeId();
        $propertyTypeGraph = $this->typeGraph($propertyTypeId, $depth - 1);

        $graph = $graph->merge($propertyTypeGraph);
        $propertyType = $graph->node($propertyTypeId);
        assert(
            $propertyType instanceof ClassNode
            || $propertyType instanceof GraphInterfaceNode
            || $propertyType instanceof EnumNode
            || $propertyType instanceof TraitNode
            || $propertyType instanceof BuiltinNode
        );
        $graph->addEdge(new DeclarationTypePropertyEdge(
            $property,
            $propertyType,
            $this->generator->fileMeta()
        ));

        return $graph;
    }

    /**
     * Generates an enum case graph (simple node only).
     *
     * @param null|EnumCaseNodeId $symbol Optional enum case NodeId to use as root
     *
     * @return Graph The enum case graph
     */
    private function enumCaseGraph(?EnumCaseNodeId $symbol = null): Graph
    {
        $graph = new Graph();
        $enumCaseId = $symbol ?? $this->generator->enumCaseNodeId();
        $enumCase = new EnumCaseNode(
            id: $enumCaseId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($enumCase);

        return $graph;
    }

    /**
     * Generates an enum graph with its enum cases.
     *
     * @param null|EnumNodeId $symbol Optional enum NodeId to use as root
     * @param int             $depth  Maximum recursion depth (default: 20)
     *
     * @return Graph The enum graph
     */
    private function enumGraph(?EnumNodeId $symbol = null, int $depth = 5): Graph
    {
        $graph = new Graph();

        $enumId = $symbol ?? $this->generator->enumNodeId();
        $enum = new EnumNode(
            id: $enumId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($enum);

        if ($depth === 0) {
            return $graph;
        }

        $enumCaseIds = $this->generator->array(fn () => $this->generator->enumCaseNodeId(), 0, 5);
        foreach ($enumCaseIds as $enumCaseId) {
            $enumCaseGraph = $this->enumCaseGraph($enumCaseId);
            $graph = $graph->merge($enumCaseGraph);
            $enumCase = $graph->node($enumCaseId);
            assert($enumCase instanceof EnumCaseNode);
            $graph->addEdge(new DeclarationEnumCaseEdge($enum, $enumCase, $this->generator->fileMeta()));
        }

        return $graph;
    }

    /**
     * Generates a constant graph (simple node only).
     *
     * @param null|ConstantNodeId $symbol Optional constant NodeId to use as root
     *
     * @return Graph The constant graph
     */
    private function constantGraph(?ConstantNodeId $symbol = null): Graph
    {
        $graph = new Graph();
        $constantId = $symbol ?? $this->generator->constantNodeId();
        $constant = new ConstantNode(
            id: $constantId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($constant);

        return $graph;
    }

    /**
     * Generates a builtin type graph (simple node only).
     *
     * @param null|BuiltinNodeId $symbol Optional builtin NodeId to use as root
     *
     * @return Graph The builtin graph
     */
    private function builtinGraph(?BuiltinNodeId $symbol = null): Graph
    {
        $graph = new Graph();
        $builtinId = $symbol ?? $this->generator->builtinNodeId();
        $builtin = new BuiltinNode(
            id: $builtinId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
        $graph->addNode($builtin);

        return $graph;
    }
}
