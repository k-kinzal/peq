<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge\Usage\ConstFetchEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\ClassConstFetch;
use PHPStan\Analyser\Scope;

/**
 * Records a constant being read.
 *
 * Only a fetch written inside a function or a method is recorded: a constant read in
 * a property default or another constant expression has no callable to attribute the
 * read to, and the graph has nowhere to hang the relation.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class ConstFetchProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param ClassConstFetch $node       The syntax node met during analysis
     * @param Scope           $scope      The analyser scope it was written in
     * @param null|Node       $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<ConstFetchEdge> The relations it describes
     */
    public static function process(ClassConstFetch $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name && $node->name instanceof PhpParserNode\Identifier) {
            $className = $scope->resolveName($node->class);
            if (!(new QualifiedName($className))->isBuiltinType()) {
                $constName = $node->name->toString();
                $targetNode = new ConstantNode(
                    ConstantNodeId::of($className, $constName),
                    false,
                    null
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1, $node->getStartFilePos());
                    $items[] = new ConstFetchEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
