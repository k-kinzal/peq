<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
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
 * Faker provider for generating atomic graph elements.
 *
 * This provider generates individual graph components such as NodeIds, EdgeKinds,
 * and FileMeta objects. These are the fundamental building blocks used to construct
 * more complex graph structures.
 *
 * @property GraphGenerator $generator
 */
final class AtomicProvider extends Base
{
    /**
     * Generates a random NodeKind.
     *
     * @return NodeKind One of the available node kind enumeration values
     */
    public function nodeKind(): NodeKind
    {
        $kinds = NodeKind::cases();

        return $kinds[$this->generator->numberBetween(0, count($kinds) - 1)];
    }

    /**
     * Generates a random EdgeKind.
     *
     * Excludes UsedBy and DeclaredIn as these are reverse edge kinds that should
     * only be created automatically by Graph::addEdge(), not generated directly.
     *
     * @return EdgeKind One of the available edge kind enumeration values
     */
    public function edgeKind(): EdgeKind
    {
        $kinds = array_values(array_filter(
            EdgeKind::cases(),
            fn ($kind) => $kind !== EdgeKind::UsedBy && $kind !== EdgeKind::DeclaredIn
        ));

        return $kinds[$this->generator->numberBetween(0, count($kinds) - 1)];
    }

    /**
     * Generates a NodeId for a specific or random node kind.
     *
     * @param null|NodeKind $kind The kind of node to generate an ID for, or null for random
     *
     * @return NodeId<Node> A node identifier appropriate for the specified kind
     */
    public function nodeId(?NodeKind $kind = null): NodeId
    {
        $kind ??= $this->nodeKind();

        return match ($kind) {
            NodeKind::Klass => $this->classNodeId(),
            NodeKind::Interface => $this->interfaceNodeId(),
            NodeKind::Trait => $this->traitNodeId(),
            NodeKind::Enum => $this->enumNodeId(),
            NodeKind::Method => $this->methodNodeId(),
            NodeKind::Property => $this->propertyNodeId(),
            NodeKind::Function => $this->functionNodeId(),
            NodeKind::Constant => $this->constantNodeId(),
            NodeKind::EnumCase => $this->enumCaseNodeId(),
            NodeKind::Builtin => $this->builtinNodeId(),
            NodeKind::Unknown => $this->unknownNodeId(),
        };
    }

    /**
     * Generates a NodeId for a class.
     *
     * @return ClassNodeId Class node identifier (e.g., "App\Service\UserService")
     */
    public function classNodeId(): ClassNodeId
    {
        return new ClassNodeId($this->generator->namespace(), $this->generator->className());
    }

    /**
     * Generates a NodeId for an interface.
     *
     * @return InterfaceNodeId Interface node identifier (e.g., "App\Service\UserServiceInterface")
     */
    public function interfaceNodeId(): InterfaceNodeId
    {
        return new InterfaceNodeId($this->generator->namespace(), $this->generator->interfaceName());
    }

    /**
     * Generates a NodeId for a trait.
     *
     * @return TraitNodeId Trait node identifier (e.g., "App\Trait\LoggableTrait")
     */
    public function traitNodeId(): TraitNodeId
    {
        return new TraitNodeId($this->generator->namespace(), $this->generator->traitName());
    }

    /**
     * Generates a NodeId for an enum.
     *
     * @return EnumNodeId Enum node identifier (e.g., "App\Enum\StatusEnum")
     */
    public function enumNodeId(): EnumNodeId
    {
        return new EnumNodeId($this->generator->namespace(), $this->generator->enumName());
    }

    /**
     * Generates a NodeId for a method.
     *
     * @return MethodNodeId Method node identifier (e.g., "App\Service\UserService::getData")
     */
    public function methodNodeId(): MethodNodeId
    {
        return new MethodNodeId(
            $this->generator->namespace(),
            $this->generator->className(),
            $this->generator->methodName()
        );
    }

    /**
     * Generates a NodeId for a property.
     *
     * @return PropertyNodeId Property node identifier (e.g., "App\Service\UserService::userData")
     */
    public function propertyNodeId(): PropertyNodeId
    {
        return new PropertyNodeId(
            $this->generator->namespace(),
            $this->generator->className(),
            $this->generator->propertyName()
        );
    }

    /**
     * Generates a NodeId for a function.
     *
     * @return FunctionNodeId Function node identifier (e.g., "App\Service\parseData")
     */
    public function functionNodeId(): FunctionNodeId
    {
        return new FunctionNodeId($this->generator->namespace(), $this->generator->functionName());
    }

    /**
     * Generates a NodeId for a constant.
     *
     * @return ConstantNodeId Constant node identifier (e.g., "App\Service\UserService::MAX_SIZE")
     */
    public function constantNodeId(): ConstantNodeId
    {
        return new ConstantNodeId(
            $this->generator->namespace(),
            $this->generator->className(),
            $this->generator->constantName()
        );
    }

    /**
     * Generates a NodeId for an enum case.
     *
     * @return EnumCaseNodeId Enum case node identifier (e.g., "App\Enum\StatusEnum::ACTIVE")
     */
    public function enumCaseNodeId(): EnumCaseNodeId
    {
        return new EnumCaseNodeId(
            $this->generator->namespace(),
            $this->generator->enumName(),
            $this->generator->enumCaseName()
        );
    }

    /**
     * Generates a NodeId for a builtin type.
     *
     * @return BuiltinNodeId Builtin type node identifier (e.g., "App\Service\StringType")
     */
    public function builtinNodeId(): BuiltinNodeId
    {
        return new BuiltinNodeId($this->generator->namespace(), $this->generator->pascalCase());
    }

    /**
     * Generates a NodeId for an unknown type.
     *
     * @return UnknownNodeId Unknown type node identifier
     */
    public function unknownNodeId(): UnknownNodeId
    {
        return new UnknownNodeId($this->generator->pascalCase());
    }

    /**
     * Generates a NodeId for a type node (builtin, class, interface, or enum case).
     */
    public function typeNodeId(): BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId
    {
        /** @var NodeKind $kind */
        $kind = $this->generator->randomElement([
            NodeKind::Builtin, NodeKind::Klass, NodeKind::Interface, NodeKind::Enum,
        ]);

        /** @var BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId */

        return $this->generator->nodeId($kind);
    }

    /**
     * Generates file metadata for a code element.
     *
     * @return FileMeta File location metadata with path, line, and column information
     */
    public function fileMeta(): FileMeta
    {
        return new FileMeta(
            path: $this->generator->phpFilePath(),
            line: $this->generator->numberBetween(1, 100),
            column: 1
        );
    }
}
