<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\ConstFetchEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\ClassConstFetch;
use PHPStan\Analyser\Scope;

final class ConstFetchProcessor
{
    /**
     * @return array<ConstFetchEdge>
     */
    public static function process(ClassConstFetch $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name && $node->name instanceof PhpParserNode\Identifier) {
            $className = $scope->resolveName($node->class);
            if (!SourceResolver::isBuiltin($className)) {
                $constName = $node->name->toString();
                $targetNode = new ConstantNode(
                    new ConstantNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className), $constName),
                    false,
                    null
                );
                // ConstFetchEdge requires FunctionNode or MethodNode as source.
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                    $items[] = new ConstFetchEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
