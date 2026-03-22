<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Provider;

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
use Faker\Provider\Base;

/**
 * Faker provider for generating complete graph components (nodes and edges).
 *
 * This provider builds on atomic elements to create fully-formed Node and Edge objects
 * with appropriate relationships and metadata. It provides methods for generating all
 * types of PHP code elements and their interconnections.
 *
 * @property GraphGenerator $generator
 */
final class ComponentProvider extends Base
{
    /**
     * Generates a complete Node for a specific or random node kind.
     *
     * @param null|NodeKind $kind The kind of node to generate, or null for random
     *
     * @return Node A fully-formed node with ID, kind, and metadata
     */
    public function node(?NodeKind $kind = null): Node
    {
        $kind ??= $this->generator->nodeKind();

        return match ($kind) {
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
     * @param null|ClassNodeId $nodeId Optional specific ClassNodeId, or null for random
     *
     * @return ClassNode Node representing a PHP class
     */
    public function classNode(?ClassNodeId $nodeId = null): ClassNode
    {
        $nodeId ??= $this->generator->classNodeId();

        return new ClassNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates an interface node.
     *
     * @param null|InterfaceNodeId $nodeId Optional specific InterfaceNodeId, or null for random
     *
     * @return GraphInterfaceNode Node representing a PHP interface
     */
    public function interfaceNode(?InterfaceNodeId $nodeId = null): GraphInterfaceNode
    {
        $nodeId ??= $this->generator->interfaceNodeId();

        return new GraphInterfaceNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a trait node.
     *
     * @param null|TraitNodeId $nodeId Optional specific TraitNodeId, or null for random
     *
     * @return TraitNode Node representing a PHP trait
     */
    public function traitNode(?TraitNodeId $nodeId = null): TraitNode
    {
        $nodeId ??= $this->generator->traitNodeId();

        return new TraitNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates an enum case node.
     *
     * @param null|EnumCaseNodeId $nodeId Optional specific EnumCaseNodeId, or null for random
     *
     * @return EnumCaseNode Node representing an enum case
     */
    public function enumCaseNode(?EnumCaseNodeId $nodeId = null): EnumCaseNode
    {
        $nodeId ??= $this->generator->enumCaseNodeId();

        return new EnumCaseNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta(),
        );
    }

    /**
     * Generates an enum node.
     *
     * @param null|EnumNodeId $nodeId Optional specific EnumNodeId, or null for random
     *
     * @return EnumNode Node representing a PHP enum
     */
    public function enumNode(?EnumNodeId $nodeId = null): EnumNode
    {
        $nodeId ??= $this->generator->enumNodeId();

        return new EnumNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a method node.
     *
     * @param null|MethodNodeId $nodeId Optional specific MethodNodeId, or null for random
     *
     * @return MethodNode Node representing a class method
     */
    public function methodNode(?MethodNodeId $nodeId = null): MethodNode
    {
        $nodeId ??= $this->generator->methodNodeId();

        return new MethodNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a property node.
     *
     * @param null|PropertyNodeId $nodeId Optional specific PropertyNodeId, or null for random
     *
     * @return PropertyNode Node representing a class property
     */
    public function propertyNode(?PropertyNodeId $nodeId = null): PropertyNode
    {
        $nodeId ??= $this->generator->propertyNodeId();

        return new PropertyNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a function node.
     *
     * @param null|FunctionNodeId $nodeId Optional specific FunctionNodeId, or null for random
     *
     * @return FunctionNode Node representing a global function
     */
    public function functionNode(?FunctionNodeId $nodeId = null): FunctionNode
    {
        $nodeId ??= $this->generator->functionNodeId();

        return new FunctionNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a constant node.
     *
     * @param null|ConstantNodeId $nodeId Optional specific ConstantNodeId, or null for random
     *
     * @return ConstantNode Node representing a class constant
     */
    public function constantNode(?ConstantNodeId $nodeId = null): ConstantNode
    {
        $nodeId ??= $this->generator->constantNodeId();

        return new ConstantNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a builtin type node.
     *
     * @param null|BuiltinNodeId $nodeId Optional specific BuiltinNodeId, or null for random
     *
     * @return BuiltinNode Node representing a PHP builtin type (string, int, etc.)
     */
    public function builtinNode(?BuiltinNodeId $nodeId = null): BuiltinNode
    {
        $nodeId ??= $this->generator->builtinNodeId();

        return new BuiltinNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates an unknown type node.
     *
     * @param null|UnknownNodeId $nodeId Optional specific UnknownNodeId, or null for random
     *
     * @return UnknownNode Node representing an unresolved or unknown type
     */
    public function unknownNode(?UnknownNodeId $nodeId = null): UnknownNode
    {
        $nodeId ??= $this->generator->unknownNodeId();

        return new UnknownNode(
            id: $nodeId,
            resolved: $this->generator->boolean(),
            meta: $this->generator->fileMeta()
        );
    }

    /**
     * Generates a type node (builtin, class, interface, or enum)
     * for a specific or random type.
     *
     * @param null|BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $nodeId The specific type node ID, or null for random
     *
     * @return BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode The generated type node
     */
    public function typeNode(
        BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId|null $nodeId = null
    ): BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode {
        if ($nodeId === null) {
            $nodeId = $this->generator->typeNodeId();
        }

        return match (true) {
            $nodeId instanceof BuiltinNodeId => $this->builtinNode($nodeId),
            $nodeId instanceof ClassNodeId => $this->classNode($nodeId),
            $nodeId instanceof InterfaceNodeId => $this->interfaceNode($nodeId),
            $nodeId instanceof EnumNodeId => $this->enumNode($nodeId),
        };
    }
}
