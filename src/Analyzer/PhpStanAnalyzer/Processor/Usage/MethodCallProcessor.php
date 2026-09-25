<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PHPStan\Analyser\Scope;

/**
 * Processes instance method calls via $this and $this? receivers.
 *
 * This source pass records lexical $this calls. Once every declaration is known,
 * CallEnrichment resolves other receivers and adds possible implementation bodies
 * for both engines using the same graph vocabulary and type constraints.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class MethodCallProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param MethodCall|NullsafeMethodCall $node       The syntax node met during analysis
     * @param Scope                         $scope      The analyser scope it was written in
     * @param null|Node                     $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<MethodCallEdge> The relations it describes
     */
    public static function process(MethodCall|NullsafeMethodCall $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->var instanceof PhpParserNode\Expr\Variable
            && is_string($node->var->name)
            && $node->var->name === 'this'
            && $node->name instanceof PhpParserNode\Identifier
            && $scope->isInClass()
        ) {
            $className = $scope->getClassReflection()->getName();
            $methodName = $node->name->toString();
            $targetNode = new MethodNode(
                MethodNodeId::of($className, $methodName),
                false,
                null,
            );
            if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1, $node->getStartFilePos(), $node->getEndFilePos() < 0 ? null : $node->getEndFilePos());
                $items[] = new MethodCallEdge($sourceNode, $targetNode, $meta);
            }
        }

        return $items;
    }
}
