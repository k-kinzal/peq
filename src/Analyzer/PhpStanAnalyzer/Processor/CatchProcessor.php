<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\CatchEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node\Stmt\Catch_;
use PHPStan\Analyser\Scope;

final class CatchProcessor
{
    /**
     * @return array<CatchEdge>
     */
    public static function process(Catch_ $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        foreach ($node->types as $type) {
            $className = $type->toString(); // Catch types are Names
            $targetNode = new ClassNode(
                new ClassNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className)),
                false,
                null
            );
            if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                $items[] = new CatchEdge($sourceNode, $targetNode, $meta);
            }
        }

        return $items;
    }
}
