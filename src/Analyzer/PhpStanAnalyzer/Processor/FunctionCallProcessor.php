<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;

final class FunctionCallProcessor
{
    /**
     * @return list<FunctionCallEdge>
     */
    public static function process(FuncCall $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->name instanceof PhpParserNode\Name) {
            $functionName = $node->name->toString();
            if (!SourceResolver::isBuiltin($functionName)) {
                $targetNode = new FunctionNode(
                    new FunctionNodeId(SourceResolver::getNamespace($functionName), SourceResolver::getShortName($functionName)),
                    false,
                    null,
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                    $items[] = new FunctionCallEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
