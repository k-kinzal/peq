<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;

/**
 * Generates the symbols that relate to nothing further.
 *
 * A class constant, an enum case and a builtin type each end a branch of the graph:
 * they are declared or used but declare and use nothing themselves. Because they
 * never recurse, they need no way back into the other generators, and their depth
 * argument would have nothing to spend — which is why they take none.
 *
 * @visibility parent
 */
final class LeafGraphGenerator
{
    /**
     * @param NodeGenerator $nodes The source of the nodes these graphs hold
     */
    public function __construct(
        private readonly NodeGenerator $nodes,
    ) {}

    /**
     * Generates the graph of a class constant.
     *
     * @param null|ConstantNodeId $symbol The constant to generate, or null to draw one
     *
     * @return GeneratedGraph<ConstantNode> A graph holding the constant alone
     */
    public function constantGraph(?ConstantNodeId $symbol = null): GeneratedGraph
    {
        return GeneratedGraph::rootedAt($this->nodes->constantNode($symbol));
    }

    /**
     * Generates the graph of an enum case.
     *
     * @param null|EnumCaseNodeId $symbol The enum case to generate, or null to draw one
     *
     * @return GeneratedGraph<EnumCaseNode> A graph holding the enum case alone
     */
    public function enumCaseGraph(?EnumCaseNodeId $symbol = null): GeneratedGraph
    {
        return GeneratedGraph::rootedAt($this->nodes->enumCaseNode($symbol));
    }

    /**
     * Generates the graph of a builtin type.
     *
     * @param null|BuiltinNodeId $symbol The builtin type to generate, or null to draw one
     *
     * @return GeneratedGraph<BuiltinNode> A graph holding the builtin type alone
     */
    public function builtinGraph(?BuiltinNodeId $symbol = null): GeneratedGraph
    {
        return GeneratedGraph::rootedAt($this->nodes->builtinNode($symbol));
    }
}
