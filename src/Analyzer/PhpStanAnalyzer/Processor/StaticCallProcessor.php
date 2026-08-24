<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;

/**
 * Records a static method being called.
 *
 * The receiving class is written out at the call site, so the relation is resolvable
 * without inferring the type of any expression.
 *
 * @visibility parent
 */
final class StaticCallProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param StaticCall $node       The syntax node met during analysis
     * @param Scope      $scope      The analyser scope it was written in
     * @param null|Node  $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<StaticCallEdge> The relations it describes
     */
    public static function process(StaticCall $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name && $node->name instanceof PhpParserNode\Identifier) {
            $className = $scope->resolveName($node->class);
            if (!(new QualifiedName($className))->isBuiltinType()) {
                $methodName = $node->name->toString();
                $targetNode = new MethodNode(
                    MethodNodeId::of($className, $methodName),
                    false,
                    null
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                    $items[] = new StaticCallEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
