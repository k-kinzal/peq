<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\StaticPropertyAccessEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PHPStan\Analyser\Scope;

final class StaticPropertyAccessProcessor
{
    /**
     * @return list<StaticPropertyAccessEdge>
     */
    public static function process(StaticPropertyFetch $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name && $node->name instanceof PhpParserNode\VarLikeIdentifier) {
            $className = $scope->resolveName($node->class);
            if (!SourceResolver::isBuiltin($className)) {
                $propertyName = $node->name->toString();
                $targetNode = new PropertyNode(
                    new PropertyNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className), $propertyName),
                    false,
                    null,
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                    $items[] = new StaticPropertyAccessEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
