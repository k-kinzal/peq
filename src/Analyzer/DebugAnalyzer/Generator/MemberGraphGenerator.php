<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\TypeParameterEdge;
use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeReturnEdge;
use App\Analyzer\Graph\Edge\Usage\ConstFetchEdge;
use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\Usage\StaticCallEdge;
use App\Analyzer\Graph\Edge\Usage\StaticPropertyAccessEdge;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;

/**
 * Generates the symbols a class-like declares: methods, functions and properties.
 *
 * A method and a function have the same signature shape — a return type and some
 * parameter types — so that part is generated once for both. What differs is the
 * body: a method may reach a method, a function, a property, a constant or a
 * constructor call, while a function only calls functions, and a property has no
 * body at all and only a declared type.
 *
 * @visibility parent
 */
final readonly class MemberGraphGenerator
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
     * Generates the graph of a method: its signature and what its body reaches.
     *
     * @param SymbolGraphGenerator $symbols The contract the referenced symbols are generated through
     * @param null|MethodNodeId    $symbol  The method to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<MethodNode> The method graph
     */
    public function methodGraph(SymbolGraphGenerator $symbols, ?MethodNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->methodNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        $result = $this->callableSignature($symbols, $result, $depth);

        return $this->methodAccesses($symbols, $this->methodCalls($symbols, $result, $depth), $depth);
    }

    /**
     * Generates the graph of a function: its signature and the functions it calls.
     *
     * @param SymbolGraphGenerator $symbols The contract the referenced symbols are generated through
     * @param null|FunctionNodeId  $symbol  The function to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<FunctionNode> The function graph
     */
    public function functionGraph(SymbolGraphGenerator $symbols, ?FunctionNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->functionNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        $result = $this->callableSignature($symbols, $result, $depth);
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->functionGraph(null, $depth - 1),
                fn (FunctionNode $caller, FunctionNode $called): Edge => new FunctionCallEdge($caller, $called, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Generates the graph of a property: its declared type.
     *
     * @param SymbolGraphGenerator $symbols The contract the declared type is generated through
     * @param null|PropertyNodeId  $symbol  The property to generate, or null to draw one
     * @param int                  $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<PropertyNode> The property graph
     */
    public function propertyGraph(SymbolGraphGenerator $symbols, ?PropertyNodeId $symbol = null, int $depth = 5): GeneratedGraph
    {
        $result = GeneratedGraph::rootedAt($this->nodes->propertyNode($symbol));
        if ($depth === 0) {
            return $result;
        }

        return $result->relatedTo(
            $symbols->typeGraph(null, $depth - 1),
            fn (PropertyNode $property, BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode $type): Edge => new TypePropertyEdge($property, $type, $this->ids->fileMeta()),
        );
    }

    /**
     * Adds the return type and the parameter types a callable declares.
     *
     * @template TCallable of FunctionNode|MethodNode
     *
     * @param SymbolGraphGenerator      $symbols The contract the declared types are generated through
     * @param GeneratedGraph<TCallable> $result  The callable graph so far
     * @param int                       $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<TCallable> The callable graph with its declared types
     */
    public function callableSignature(SymbolGraphGenerator $symbols, GeneratedGraph $result, int $depth): GeneratedGraph
    {
        $result = $result->relatedTo(
            $symbols->typeGraph(null, $depth - 1),
            fn (FunctionNode|MethodNode $callable, BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode $type): Edge => new TypeReturnEdge($callable, $type, $this->ids->fileMeta()),
        );

        for ($remaining = $this->random->numberBetween(0, 5); $remaining > 0; --$remaining) {
            $result = $result->relatedTo(
                $symbols->typeGraph(null, $depth - 1),
                fn (FunctionNode|MethodNode $callable, BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode $type): Edge => new TypeParameterEdge($callable, $type, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Adds the methods and functions a method body calls.
     *
     * @param SymbolGraphGenerator       $symbols The contract the called symbols are generated through
     * @param GeneratedGraph<MethodNode> $result  The method graph so far
     * @param int                        $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<MethodNode> The method graph with the calls its body makes
     */
    public function methodCalls(SymbolGraphGenerator $symbols, GeneratedGraph $result, int $depth): GeneratedGraph
    {
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->methodGraph(null, $depth - 1),
                fn (MethodNode $caller, MethodNode $called): Edge => new MethodCallEdge($caller, $called, $this->ids->fileMeta()),
            );
        }
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->methodGraph(null, $depth - 1),
                fn (MethodNode $caller, MethodNode $called): Edge => new StaticCallEdge($caller, $called, $this->ids->fileMeta()),
            );
        }
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->functionGraph(null, $depth - 1),
                fn (MethodNode $caller, FunctionNode $called): Edge => new FunctionCallEdge($caller, $called, $this->ids->fileMeta()),
            );
        }

        return $result;
    }

    /**
     * Adds the properties, constants and constructors a method body reaches.
     *
     * @param SymbolGraphGenerator       $symbols The contract the reached symbols are generated through
     * @param GeneratedGraph<MethodNode> $result  The method graph so far
     * @param int                        $depth   How many further levels of symbols to generate
     *
     * @return GeneratedGraph<MethodNode> The method graph with what its body reaches
     */
    public function methodAccesses(SymbolGraphGenerator $symbols, GeneratedGraph $result, int $depth): GeneratedGraph
    {
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->propertyGraph(null, $depth - 1),
                fn (MethodNode $reader, PropertyNode $property): Edge => new PropertyAccessEdge($reader, $property, $this->ids->fileMeta()),
            );
        }
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->propertyGraph(null, $depth - 1),
                fn (MethodNode $reader, PropertyNode $property): Edge => new StaticPropertyAccessEdge($reader, $property, $this->ids->fileMeta()),
            );
        }
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->constantGraph(),
                fn (MethodNode $reader, ConstantNode $constant): Edge => new ConstFetchEdge($reader, $constant, $this->ids->fileMeta()),
            );
        }
        if ($this->random->boolean()) {
            $result = $result->relatedTo(
                $symbols->classGraph(null, $depth - 1),
                fn (MethodNode $caller, ClassNode $instantiated): Edge => new InstantiationEdge($caller, $instantiated, $this->ids->fileMeta()),
            );
        }

        return $result;
    }
}
