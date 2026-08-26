<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\EnumCaseEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;

/**
 * Records the cases an enum declares.
 *
 * An enum case is a symbol of its own — code names it directly — so it becomes a node
 * related to the enum that declares it, along with any attributes written on it.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class EnumCaseProcessor
{
    /**
     * Records the case and the enum that declares it.
     *
     * @param EnumCase $node  The syntax node met during analysis
     * @param Scope    $scope The analyser scope it was written in
     *
     * @return list<AttributeEdge|EnumCaseEdge|EnumCaseNode> The relations it describes
     */
    public static function process(EnumCase $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $className = $classReflection->getName();
        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
        $declared = new EnumCaseNode(EnumCaseNodeId::of($className, $node->name->toString()), true, $meta);

        return [
            $declared,
            new EnumCaseEdge(new EnumNode(EnumNodeId::of($className), true, null), $declared, $meta),
            ...AttributeProcessor::process($node->attrGroups, $declared, $scope),
        ];
    }
}
