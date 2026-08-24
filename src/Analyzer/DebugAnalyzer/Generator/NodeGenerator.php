<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\Node;
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
use App\Analyzer\Graph\Node\UnknownNode;
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
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use Faker\Generator;

/**
 * Generates the nodes a graph is made of.
 *
 * Each operation takes the identifier type its kind of node is keyed by and returns
 * that kind of node, so a generated node can be handed straight to the edge
 * constructors that accept it. Passing an identifier reuses a symbol that has
 * already been named; omitting it draws a fresh one.
 *
 * @visibility parent
 */
final class NodeGenerator
{
    /**
     * @param NodeIdGenerator $ids   The source of identifiers and source locations
     * @param Generator       $faker The random source resolution flags are drawn from
     */
    public function __construct(
        private readonly NodeIdGenerator $ids,
        private readonly Generator $faker,
    ) {}

    /**
     * Generates a node of the given kind.
     *
     * @param null|NodeKind $kind The kind of node to generate, or null to draw one
     *
     * @return Node A node of that kind
     */
    public function node(?NodeKind $kind = null): Node
    {
        return match ($kind ?? $this->ids->nodeKind()) {
            NodeKind::Klass => $this->classNode(),
            NodeKind::Interface => $this->interfaceNode(),
            NodeKind::Trait => $this->traitNode(),
            NodeKind::Enum => $this->enumNode(),
            NodeKind::Method => $this->methodNode(),
            NodeKind::Property => $this->propertyNode(),
            NodeKind::Function => $this->functionNode(),
            NodeKind::Constant => $this->constantNode(),
            NodeKind::EnumCase => $this->enumCaseNode(),
            NodeKind::Builtin => $this->builtinNode(),
            NodeKind::Unknown => $this->unknownNode(),
        };
    }

    /**
     * Generates a class node.
     *
     * @param null|ClassNodeId $nodeId The class to generate a node for, or null to draw one
     *
     * @return ClassNode A node representing a PHP class
     */
    public function classNode(?ClassNodeId $nodeId = null): ClassNode
    {
        return new ClassNode(
            id: $nodeId ?? $this->ids->classNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates an interface node.
     *
     * @param null|InterfaceNodeId $nodeId The interface to generate a node for, or null to draw one
     *
     * @return GraphInterfaceNode A node representing a PHP interface
     */
    public function interfaceNode(?InterfaceNodeId $nodeId = null): GraphInterfaceNode
    {
        return new GraphInterfaceNode(
            id: $nodeId ?? $this->ids->interfaceNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a trait node.
     *
     * @param null|TraitNodeId $nodeId The trait to generate a node for, or null to draw one
     *
     * @return TraitNode A node representing a PHP trait
     */
    public function traitNode(?TraitNodeId $nodeId = null): TraitNode
    {
        return new TraitNode(
            id: $nodeId ?? $this->ids->traitNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates an enum node.
     *
     * @param null|EnumNodeId $nodeId The enum to generate a node for, or null to draw one
     *
     * @return EnumNode A node representing a PHP enum
     */
    public function enumNode(?EnumNodeId $nodeId = null): EnumNode
    {
        return new EnumNode(
            id: $nodeId ?? $this->ids->enumNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates an enum case node.
     *
     * @param null|EnumCaseNodeId $nodeId The enum case to generate a node for, or null to draw one
     *
     * @return EnumCaseNode A node representing an enum case
     */
    public function enumCaseNode(?EnumCaseNodeId $nodeId = null): EnumCaseNode
    {
        return new EnumCaseNode(
            id: $nodeId ?? $this->ids->enumCaseNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a method node.
     *
     * @param null|MethodNodeId $nodeId The method to generate a node for, or null to draw one
     *
     * @return MethodNode A node representing a class method
     */
    public function methodNode(?MethodNodeId $nodeId = null): MethodNode
    {
        return new MethodNode(
            id: $nodeId ?? $this->ids->methodNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a property node.
     *
     * @param null|PropertyNodeId $nodeId The property to generate a node for, or null to draw one
     *
     * @return PropertyNode A node representing a class property
     */
    public function propertyNode(?PropertyNodeId $nodeId = null): PropertyNode
    {
        return new PropertyNode(
            id: $nodeId ?? $this->ids->propertyNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a function node.
     *
     * @param null|FunctionNodeId $nodeId The function to generate a node for, or null to draw one
     *
     * @return FunctionNode A node representing a global function
     */
    public function functionNode(?FunctionNodeId $nodeId = null): FunctionNode
    {
        return new FunctionNode(
            id: $nodeId ?? $this->ids->functionNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a class constant node.
     *
     * @param null|ConstantNodeId $nodeId The constant to generate a node for, or null to draw one
     *
     * @return ConstantNode A node representing a class constant
     */
    public function constantNode(?ConstantNodeId $nodeId = null): ConstantNode
    {
        return new ConstantNode(
            id: $nodeId ?? $this->ids->constantNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a builtin type node.
     *
     * @param null|BuiltinNodeId $nodeId The builtin type to generate a node for, or null to draw one
     *
     * @return BuiltinNode A node representing a PHP builtin type
     */
    public function builtinNode(?BuiltinNodeId $nodeId = null): BuiltinNode
    {
        return new BuiltinNode(
            id: $nodeId ?? $this->ids->builtinNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates an unresolved node.
     *
     * @param null|UnknownNodeId $nodeId The symbol to generate a node for, or null to draw one
     *
     * @return UnknownNode A node representing a symbol analysis could not resolve
     */
    public function unknownNode(?UnknownNodeId $nodeId = null): UnknownNode
    {
        return new UnknownNode(
            id: $nodeId ?? $this->ids->unknownNodeId(),
            resolved: $this->faker->boolean(),
            meta: $this->ids->fileMeta(),
        );
    }

    /**
     * Generates a node for something that can stand in a type position.
     *
     * @param null|BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $nodeId The type to generate a node for, or null to draw one
     *
     * @return BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode A node usable as a declared type
     */
    public function typeNode(
        BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId|null $nodeId = null,
    ): BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode {
        $nodeId ??= $this->ids->typeNodeId();

        return match (true) {
            $nodeId instanceof BuiltinNodeId => $this->builtinNode($nodeId),
            $nodeId instanceof ClassNodeId => $this->classNode($nodeId),
            $nodeId instanceof InterfaceNodeId => $this->interfaceNode($nodeId),
            $nodeId instanceof EnumNodeId => $this->enumNode($nodeId),
        };
    }
}
