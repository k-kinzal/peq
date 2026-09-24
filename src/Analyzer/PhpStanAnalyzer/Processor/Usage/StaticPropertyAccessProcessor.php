<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge\Usage\StaticPropertyAccessEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PHPStan\Analyser\Scope;

/**
 * Records a static property being read or written.
 *
 * As with a static call, the owning class is named at the access site, so the
 * relation is resolvable from the written name alone.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class StaticPropertyAccessProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param StaticPropertyFetch $node       The syntax node met during analysis
     * @param Scope               $scope      The analyser scope it was written in
     * @param null|Node           $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<StaticPropertyAccessEdge> The relations it describes
     */
    public static function process(StaticPropertyFetch $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name && $node->name instanceof PhpParserNode\VarLikeIdentifier) {
            $className = $scope->resolveName($node->class);
            if (!(new QualifiedName($className))->isBuiltinType()) {
                $propertyName = $node->name->toString();
                $targetNode = new PropertyNode(
                    PropertyNodeId::of($className, $propertyName),
                    false,
                    null,
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1, $node->getStartFilePos());
                    $items[] = new StaticPropertyAccessEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
