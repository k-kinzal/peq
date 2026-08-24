<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

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

/**
 * Generating the graph rooted at any one kind of symbol.
 *
 * PHP symbols refer to each other in every direction — a class declares methods, a
 * method instantiates a class — so the generators for them are mutually recursive.
 * This contract is what they recurse through: a generator that needs a method graph
 * asks for one here instead of holding the method generator, which is what keeps the
 * generators from having to be constructed in a cycle.
 *
 * Every operation takes the identifier of the symbol to generate, or null to draw a
 * fresh one, and a remaining depth. Depth zero produces the root symbol alone, which
 * is what makes the recursion terminate.
 *
 * @visibility parent
 */
interface SymbolGraphGenerator
{
    /**
     * Generates the graph of a class: its members and what it inherits from.
     *
     * @param null|ClassNodeId $symbol The class to generate, or null to draw one
     * @param int              $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<ClassNode> The class graph
     */
    public function classGraph(?ClassNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of an interface: its methods, constants and parent.
     *
     * @param null|InterfaceNodeId $symbol The interface to generate, or null to draw one
     * @param int                  $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<GraphInterfaceNode> The interface graph
     */
    public function interfaceGraph(?InterfaceNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of a trait: its methods and properties.
     *
     * @param null|TraitNodeId $symbol The trait to generate, or null to draw one
     * @param int              $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<TraitNode> The trait graph
     */
    public function traitGraph(?TraitNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of an enum: its cases.
     *
     * @param null|EnumNodeId $symbol The enum to generate, or null to draw one
     * @param int             $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<EnumNode> The enum graph
     */
    public function enumGraph(?EnumNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of a method: its signature and what its body reaches.
     *
     * @param null|MethodNodeId $symbol The method to generate, or null to draw one
     * @param int               $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<MethodNode> The method graph
     */
    public function methodGraph(?MethodNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of a function: its signature and what its body reaches.
     *
     * @param null|FunctionNodeId $symbol The function to generate, or null to draw one
     * @param int                 $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<FunctionNode> The function graph
     */
    public function functionGraph(?FunctionNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of a property: its declared type.
     *
     * @param null|PropertyNodeId $symbol The property to generate, or null to draw one
     * @param int                 $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<PropertyNode> The property graph
     */
    public function propertyGraph(?PropertyNodeId $symbol = null, int $depth = 5): GeneratedGraph;

    /**
     * Generates the graph of a class constant, which relates to nothing further.
     *
     * @param null|ConstantNodeId $symbol The constant to generate, or null to draw one
     *
     * @return GeneratedGraph<ConstantNode> The constant graph
     */
    public function constantGraph(?ConstantNodeId $symbol = null): GeneratedGraph;

    /**
     * Generates the graph of an enum case, which relates to nothing further.
     *
     * @param null|EnumCaseNodeId $symbol The enum case to generate, or null to draw one
     *
     * @return GeneratedGraph<EnumCaseNode> The enum case graph
     */
    public function enumCaseGraph(?EnumCaseNodeId $symbol = null): GeneratedGraph;

    /**
     * Generates the graph of a builtin type, which relates to nothing further.
     *
     * @param null|BuiltinNodeId $symbol The builtin type to generate, or null to draw one
     *
     * @return GeneratedGraph<BuiltinNode> The builtin type graph
     */
    public function builtinGraph(?BuiltinNodeId $symbol = null): GeneratedGraph;

    /**
     * Generates the graph of whatever stands in a type position.
     *
     * @param null|BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId $symbol The type to generate, or null to draw one
     * @param int                                                       $depth  How many further levels of symbols to generate
     *
     * @return GeneratedGraph<BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode> The type graph
     */
    public function typeGraph(
        BuiltinNodeId|ClassNodeId|EnumNodeId|InterfaceNodeId|null $symbol = null,
        int $depth = 5,
    ): GeneratedGraph;
}
