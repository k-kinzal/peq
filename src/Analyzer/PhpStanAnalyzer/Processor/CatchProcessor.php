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

/**
 * Records the exception types a catch clause names.
 *
 * A `catch` names the classes and interfaces a piece of code is prepared to handle,
 * which is a real dependency on them: renaming one of those exception types breaks
 * the handler.
 *
 * @visibility parent
 */
final class CatchProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param Catch_    $node       The syntax node met during analysis
     * @param Scope     $scope      The analyser scope it was written in
     * @param null|Node $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<CatchEdge> The relations it describes
     */
    public static function process(Catch_ $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        foreach ($node->types as $type) {
            $className = $type->toString();
            $targetNode = new ClassNode(
                ClassNodeId::of($className),
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
