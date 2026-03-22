<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\PropertyAccessEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;

/**
 * Processes instance property access via $this and $this? receivers.
 *
 * SCOPE LIMITATION: Only $this->prop and $this?->prop are detected.
 * Access on arbitrary objects ($obj->prop) is not resolved because the
 * re-parsed AST context used by InClassMethodNodeProcessor lacks PHPStan's
 * type inference needed to determine the class of arbitrary receivers.
 */
final class PropertyAccessProcessor
{
    /**
     * @return list<PropertyAccessEdge>
     */
    public static function process(NullsafePropertyFetch|PropertyFetch $node, Scope $scope): array
    {
        $items = [];
        $sourceNode = SourceResolver::resolve($scope);

        if ($node->var instanceof PhpParserNode\Expr\Variable
            && is_string($node->var->name)
            && $node->var->name === 'this'
            && $node->name instanceof PhpParserNode\Identifier
            && $scope->isInClass()
        ) {
            $className = $scope->getClassReflection()->getName();
            $propertyName = $node->name->toString();
            $targetNode = new PropertyNode(
                new PropertyNodeId(SourceResolver::getNamespace($className), SourceResolver::getShortName($className), $propertyName),
                false,
                null,
            );
            if ($sourceNode instanceof FunctionNode || $sourceNode instanceof MethodNode) {
                $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
                $items[] = new PropertyAccessEdge($sourceNode, $targetNode, $meta);
            }
        }

        return $items;
    }
}
