<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\InstantiationEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;

final class InstantiationProcessor
{
    /**
     * @return array<InstantiationEdge>
     */
    public static function process(New_ $node, Scope $scope): array
    {
        $items = [];
        $sourceNode = SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name) {
            $className = $scope->resolveName($node->class);
            if (!SourceResolver::isBuiltin($className)) {
                $targetNode = new ClassNode(
                    new ClassNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className)),
                    false,
                    null
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                    $items[] = new InstantiationEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
