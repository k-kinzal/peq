<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;

final class StaticCallProcessor
{
    /**
     * @return array<StaticCallEdge>
     */
    public static function process(StaticCall $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name && $node->name instanceof PhpParserNode\Identifier) {
            $className = $scope->resolveName($node->class);
            if (!SourceResolver::isBuiltin($className)) {
                $methodName = $node->name->toString();
                $targetNode = new MethodNode(
                    new MethodNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className), $methodName),
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
