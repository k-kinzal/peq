<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\InstanceofEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\Instanceof_;
use PHPStan\Analyser\Scope;

/**
 * Records a type named in an instanceof test.
 *
 * Testing against a class is a dependency on it: the test stops compiling the moment
 * the class is renamed, exactly like a call would.
 *
 * @visibility parent
 */
final class InstanceofProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param Instanceof_ $node       The syntax node met during analysis
     * @param Scope       $scope      The analyser scope it was written in
     * @param null|Node   $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<InstanceofEdge> The relations it describes
     */
    public static function process(Instanceof_ $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->class instanceof PhpParserNode\Name) {
            $className = $scope->resolveName($node->class);
            if (!(new QualifiedName($className))->isBuiltinType()) {
                $targetNode = new ClassNode(
                    ClassNodeId::of($className),
                    false,
                    null
                );
                if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                    $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                    $items[] = new InstanceofEdge($sourceNode, $targetNode, $meta);
                }
            }
        }

        return $items;
    }
}
