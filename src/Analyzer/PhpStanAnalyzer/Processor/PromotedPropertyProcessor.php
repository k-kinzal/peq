<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PHPStan\Analyser\Scope;

/**
 * Records a property promoted from a constructor parameter.
 *
 * A promoted parameter declares a property, so it produces the same relations a
 * written property would: the property node, the class that declares it, its type and
 * its attributes. A parameter without a visibility modifier declares nothing and is
 * left to the processor that reads signatures.
 *
 * @visibility parent
 */
final class PromotedPropertyProcessor
{
    /**
     * Records the property a promoted constructor parameter declares.
     *
     * @param Param $node  The syntax node met during analysis
     * @param Scope $scope The analyser scope it was written in
     *
     * @return list<AttributeEdge|DeclarationPropertyEdge|DeclarationTypePropertyEdge|PropertyNode> The relations it describes
     */
    public static function process(Param $node, Scope $scope): array
    {
        if (($node->flags & Modifiers::VISIBILITY_MASK) === 0) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !$node->var instanceof Variable || !is_string($node->var->name)) {
            return [];
        }

        $className = $classReflection->getName();
        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
        $declared = new PropertyNode(PropertyNodeId::of($className, $node->var->name), true, $meta);

        $items = [
            $declared,
            new DeclarationPropertyEdge(new ClassNode(ClassNodeId::of($className), true, null), $declared, $meta),
            ...AttributeProcessor::process($node->attrGroups, $declared, $scope),
        ];

        foreach (TypeResolver::references($node->type, $scope->getFile()) as $type) {
            $items[] = new DeclarationTypePropertyEdge($declared, $type->node, $type->meta);
        }

        return $items;
    }
}
