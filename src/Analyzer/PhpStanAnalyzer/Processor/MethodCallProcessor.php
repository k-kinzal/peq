<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PHPStan\Analyser\Scope;

/**
 * Processes instance method calls via $this and $this? receivers.
 *
 * SCOPE LIMITATION: Only $this->method() and $this?->method() are detected.
 * Calls on arbitrary objects ($obj->method()) are not resolved because the
 * re-parsed AST context used by InClassMethodNodeProcessor lacks PHPStan's
 * type inference needed to determine the class of arbitrary receivers.
 */
final class MethodCallProcessor
{
    /**
     * @return list<MethodCallEdge>
     */
    public static function process(MethodCall|NullsafeMethodCall $node, Scope $scope, ?Node $sourceNode = null): array
    {
        $items = [];
        $sourceNode ??= SourceResolver::resolve($scope);

        if ($node->var instanceof PhpParserNode\Expr\Variable
            && is_string($node->var->name)
            && $node->var->name === 'this'
            && $node->name instanceof PhpParserNode\Identifier
            && $scope->isInClass()
        ) {
            $className = $scope->getClassReflection()->getName();
            $methodName = $node->name->toString();
            $targetNode = new MethodNode(
                new MethodNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className), $methodName),
                false,
                null,
            );
            if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                $items[] = new MethodCallEdge($sourceNode, $targetNode, $meta);
            }
        }

        return $items;
    }
}
