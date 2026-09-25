<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ClosureNodeId;
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

/**
 * Generates the identifiers and source locations a graph is keyed by.
 *
 * Each operation returns the identifier type the kind it names actually uses, so a
 * caller that asks for a method identifier receives a MethodNodeId and can pass it
 * straight to the edge constructors that require one.
 *
 * @visibility parent
 */
final readonly class NodeIdGenerator
{
    /**
     * @param NameGenerator $names  The source of the names identifiers are built from
     * @param RandomSource  $random The random source line numbers and choices are drawn from
     */
    public function __construct(
        private NameGenerator $names,
        private RandomSource $random,
    ) {}

    /**
     * Draws one of the kinds of node the graph can hold.
     *
     * @return NodeKind One of the node kinds, uniformly chosen
     */
    public function nodeKind(): NodeKind
    {
        $kinds = NodeKind::cases();

        return $kinds[$this->random->numberBetween(0, count($kinds) - 1)];
    }

    /**
     * Draws one of the kinds of relation source code can write.
     *
     * The two kinds the graph derives for the opposite direction are left out: they
     * are produced when an edge is recorded, never generated on their own.
     *
     * @return EdgeKind One of the authored edge kinds, uniformly chosen
     */
    public function edgeKind(): EdgeKind
    {
        $kinds = array_values(array_filter(
            EdgeKind::cases(),
            static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses,
        ));

        return $kinds[$this->random->numberBetween(0, count($kinds) - 1)];
    }

    /**
     * Generates an identifier for a node of the given kind.
     *
     * @param null|NodeKind $kind The kind to generate an identifier for, or null to draw one
     *
     * @return NodeId<Node> An identifier of the type that kind uses
     */
    public function nodeId(?NodeKind $kind = null): NodeId
    {
        return match ($kind ?? $this->nodeKind()) {
            NodeKind::Klass => $this->classNodeId(),
            NodeKind::Interface => $this->interfaceNodeId(),
            NodeKind::Trait => $this->traitNodeId(),
            NodeKind::Enum => $this->enumNodeId(),
            NodeKind::Method => $this->methodNodeId(),
            NodeKind::Property => $this->propertyNodeId(),
            NodeKind::Function => $this->functionNodeId(),
            NodeKind::Closure => $this->closureNodeId(),
            NodeKind::Constant => $this->constantNodeId(),
            NodeKind::EnumCase => $this->enumCaseNodeId(),
            NodeKind::Builtin => $this->builtinNodeId(),
            NodeKind::Unknown => $this->unknownNodeId(),
        };
    }

    /**
     * Generates an identifier for a class.
     *
     * @return ClassNodeId A class identifier, such as "App\Billing\InvoiceClass"
     */
    public function classNodeId(): ClassNodeId
    {
        return new ClassNodeId($this->names->namespace(), $this->names->className());
    }

    /**
     * Generates an identifier for an interface.
     *
     * @return InterfaceNodeId An interface identifier, such as "App\Billing\InvoiceInterface"
     */
    public function interfaceNodeId(): InterfaceNodeId
    {
        return new InterfaceNodeId($this->names->namespace(), $this->names->interfaceName());
    }

    /**
     * Generates an identifier for a trait.
     *
     * @return TraitNodeId A trait identifier, such as "App\Billing\InvoiceTrait"
     */
    public function traitNodeId(): TraitNodeId
    {
        return new TraitNodeId($this->names->namespace(), $this->names->traitName());
    }

    /**
     * Generates an identifier for an enum.
     *
     * @return EnumNodeId An enum identifier, such as "App\Billing\InvoiceEnum"
     */
    public function enumNodeId(): EnumNodeId
    {
        return new EnumNodeId($this->names->namespace(), $this->names->enumName());
    }

    /**
     * Generates an identifier for a method.
     *
     * @return MethodNodeId A method identifier, such as "App\Billing\InvoiceClass::sendMethod"
     */
    public function methodNodeId(): MethodNodeId
    {
        return new MethodNodeId(
            $this->names->namespace(),
            $this->names->className(),
            $this->names->methodName(),
        );
    }

    /**
     * Generates an identifier for a property.
     *
     * @return PropertyNodeId A property identifier, such as "App\Billing\InvoiceClass::totalProperty"
     */
    public function propertyNodeId(): PropertyNodeId
    {
        return new PropertyNodeId(
            $this->names->namespace(),
            $this->names->className(),
            $this->names->propertyName(),
        );
    }

    /**
     * Generates an identifier for a function.
     *
     * @return FunctionNodeId A function identifier, such as "App\Billing\formatFunction"
     */
    public function functionNodeId(): FunctionNodeId
    {
        return new FunctionNodeId($this->names->namespace(), $this->names->functionName());
    }

    /**
     * Generates a closure location inside a named function.
     */
    public function closureNodeId(): ClosureNodeId
    {
        return new ClosureNodeId($this->functionNodeId()->toString(), $this->random->numberBetween(1, 100), 1);
    }

    /**
     * Generates an identifier for a class constant.
     *
     * @return ConstantNodeId A constant identifier, such as "App\Billing\InvoiceClass::MAX_SIZE_CONST"
     */
    public function constantNodeId(): ConstantNodeId
    {
        return new ConstantNodeId(
            $this->names->namespace(),
            $this->names->className(),
            $this->names->constantName(),
        );
    }

    /**
     * Generates an identifier for an enum case.
     *
     * @return EnumCaseNodeId An enum case identifier, such as "App\Billing\StateEnum::OPEN_CASE"
     */
    public function enumCaseNodeId(): EnumCaseNodeId
    {
        return new EnumCaseNodeId(
            $this->names->namespace(),
            $this->names->enumName(),
            $this->names->enumCaseName(),
        );
    }

    /**
     * Generates an identifier for a builtin type.
     *
     * @return BuiltinNodeId A builtin type identifier
     */
    public function builtinNodeId(): BuiltinNodeId
    {
        return new BuiltinNodeId($this->names->namespace(), $this->names->pascalCase());
    }

    /**
     * Generates an identifier for an unresolved symbol.
     *
     * @return UnknownNodeId An unresolved symbol identifier
     */
    public function unknownNodeId(): UnknownNodeId
    {
        return new UnknownNodeId($this->names->pascalCase());
    }

    /**
     * Generates an identifier for something that can stand in a type position.
     *
     * @return BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId An identifier usable as a declared type
     */
    public function typeNodeId(): BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId
    {
        return match ($this->random->numberBetween(1, 4)) {
            1 => $this->classNodeId(),
            2 => $this->interfaceNodeId(),
            3 => $this->enumNodeId(),
            default => $this->builtinNodeId(),
        };
    }

    /**
     * Generates a source location for a node or a relation.
     *
     * @return FileMeta A location in a generated PHP file
     */
    public function fileMeta(): FileMeta
    {
        return new FileMeta(
            path: $this->names->phpFilePath(),
            line: $this->random->numberBetween(1, 100),
            column: 1,
        );
    }
}
