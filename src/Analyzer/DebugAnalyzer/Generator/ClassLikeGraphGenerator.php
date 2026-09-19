<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\ConstantEdge;
use App\Analyzer\Graph\Edge\Declaration\EnumCaseEdge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\ImplementsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Declaration\PropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TraitUseEdge;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;

/**
 * Generates the symbols that can declare members: classes, interfaces, traits, enums.
 *
 * What each of them may declare differs, and that difference is the whole content of
 * this generator: a trait declares methods and properties but nothing to inherit
 * from, an interface declares methods and constants and extends another interface,
 * an enum declares only cases. Writing those out separately is what keeps a generated
 * graph shaped like PHP rather than like a uniform tree.
 *
 * @visibility parent
 */
final readonly class ClassLikeGraphGenerator
{
    /**
     * @param NodeGenerator   $nodes  The source of the nodes these graphs hold
     * @param NodeIdGenerator $ids    The source of the source locations relations carry
     * @param RandomSource    $random The random source counts and optional relations are drawn from
     */
    public function __construct(
        private NodeGenerator $nodes,
        private NodeIdGenerator $ids,
        private RandomSource $random,
    ) {}

    /**
     * Generates the graph of a class: its members and what it inherits from.
     *
     * @param SymbolGraphGenerator $symbols The contract the member symbols are generated through
     * @param null|ClassNodeId     $symbol  The class to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<ClassNode> The class graph
     */
    public function classGraph(SymbolGraphGenerator $symbols, ?ClassNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->classNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        return $this->classInheritance($symbols, $this->classMembers($symbols, $result, $depth), $depth);
    }

    /**
     * Adds the methods, properties and constants a class declares.
     *
     * @param SymbolGraphGenerator      $symbols The contract the member symbols are generated through
     * @param GeneratedGraph<ClassNode> $result  The class graph so far
     * @param int                       $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<ClassNode> The class graph with its members
     */
    public function classMembers(SymbolGraphGenerator $symbols, GeneratedGraph $result, int $depth): GeneratedGraph
    {
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->methodGraph(null, $depth - 1),
                fn (ClassNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, $this->ids->fileMeta()),
            );
        }
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->propertyGraph(null, $depth - 1),
                fn (ClassNode $owner, PropertyNode $property): Edge => new PropertyEdge($owner, $property, $this->ids->fileMeta()),
            );
        }
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->constantGraph(),
                fn (ClassNode $owner, ConstantNode $constant): Edge => new ConstantEdge($owner, $constant, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Adds the parent class, the interfaces and the traits a class takes on.
     *
     * @param SymbolGraphGenerator      $symbols The contract the inherited symbols are generated through
     * @param GeneratedGraph<ClassNode> $result  The class graph so far
     * @param int                       $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<ClassNode> The class graph with what it inherits
     */
    public function classInheritance(SymbolGraphGenerator $symbols, GeneratedGraph $result, int $depth): GeneratedGraph
    {
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->classGraph(null, $depth - 1),
                fn (ClassNode $child, ClassNode $parent): Edge => new ExtendsEdge($child, $parent, $this->ids->fileMeta()),
            );
        }
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->interfaceGraph(null, $depth - 1),
                fn (ClassNode $owner, GraphInterfaceNode $contract): Edge => new ImplementsEdge($owner, $contract, $this->ids->fileMeta()),
            );
        }
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->traitGraph(null, $depth - 1),
                fn (ClassNode $owner, TraitNode $trait): Edge => new TraitUseEdge($owner, $trait, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Generates the graph of an interface: its methods, constants and parent.
     *
     * @param SymbolGraphGenerator $symbols The contract the member symbols are generated through
     * @param null|InterfaceNodeId $symbol  The interface to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<GraphInterfaceNode> The interface graph
     */
    public function interfaceGraph(SymbolGraphGenerator $symbols, ?InterfaceNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->interfaceNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->methodGraph(null, $depth - 1),
                fn (GraphInterfaceNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, $this->ids->fileMeta()),
            );
        }
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->constantGraph(),
                fn (GraphInterfaceNode $owner, ConstantNode $constant): Edge => new ConstantEdge($owner, $constant, $this->ids->fileMeta()),
            );
        }
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->interfaceGraph(null, $depth - 1),
                fn (GraphInterfaceNode $child, GraphInterfaceNode $parent): Edge => new ExtendsEdge($child, $parent, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Generates the graph of a trait: its methods and properties.
     *
     * @param SymbolGraphGenerator $symbols The contract the member symbols are generated through
     * @param null|TraitNodeId     $symbol  The trait to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<TraitNode> The trait graph
     */
    public function traitGraph(SymbolGraphGenerator $symbols, ?TraitNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->traitNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->methodGraph(null, $depth - 1),
                fn (TraitNode $owner, MethodNode $method): Edge => new MethodEdge($owner, $method, $this->ids->fileMeta()),
            );
        }
        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->propertyGraph(null, $depth - 1),
                fn (TraitNode $owner, PropertyNode $property): Edge => new PropertyEdge($owner, $property, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Generates the graph of an enum: its cases.
     *
     * @param SymbolGraphGenerator $symbols The contract the cases are generated through
     * @param null|EnumNodeId      $symbol  The enum to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<EnumNode> The enum graph
     */
    public function enumGraph(SymbolGraphGenerator $symbols, ?EnumNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->enumNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        for ($remaining = $this->memberCount(); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->enumCaseGraph(),
                fn (EnumNode $owner, EnumCaseNode $case): Edge => new EnumCaseEdge($owner, $case, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Draws how many members of one kind a symbol declares.
     *
     * @return int A count between none and five
     */
    public function memberCount(): int
    {
        return $this->random->numberBetween(0, 5);
    }
}
