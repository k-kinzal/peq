<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Usage;

use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProviderStaticAccessor;

/**
 * Records a function being called.
 *
 * An unqualified call inside a namespace is ambiguous on its face: PHP looks for the
 * function in the current namespace first and falls back to the global one. Which of
 * the two a call means therefore cannot be read off the name, so the name is resolved
 * the way PHP resolves it, against the functions that actually exist. Recording the
 * name as written would attach the relation to a symbol nothing declares, leaving the
 * called function looking unused.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class FunctionCallProcessor
{
    /**
     * Records what this declaration or expression brings into the graph.
     *
     * @param FuncCall  $node       The syntax node met during analysis
     * @param Scope     $scope      The analyser scope it was written in
     * @param null|Node $sourceNode The symbol it is written inside, resolved from the scope when omitted
     *
     * @return list<FunctionCallEdge> The relations it describes
     */
    public static function process(FuncCall $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->name instanceof PhpParserNode\Name) {
            $functionName = ReflectionProviderStaticAccessor::getInstance()->resolveFunctionName($node->name, $scope)
                ?? $node->name->toString();
            if (!(new QualifiedName($functionName))->isBuiltinType()) {
                $targetNode = new FunctionNode(
                    FunctionNodeId::of($functionName),
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
