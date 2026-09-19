<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\PropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;

/**
 * Records the properties a class or trait declares.
 *
 * One `public $a, $b;` statement declares several properties, each of which becomes a
 * node of its own, related to the class-like that declares it and to the type it is
 * declared as. The attributes and the type are written once for the statement, so
 * each declared property carries the same ones.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class PropertyProcessor
{
    /**
     * Records every property one statement declares.
     *
     * @param Property $node  The syntax node met during analysis
     * @param Scope    $scope The analyser scope it was written in
     *
     * @return list<AttributeEdge|PropertyEdge|PropertyNode|TypePropertyEdge> The relations it describes
     */
    public static function process(Property $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $className = $classReflection->getName();
        $owner = $classReflection->isTrait()
            ? new TraitNode(TraitNodeId::of($className), true, null)
            : new ClassNode(ClassNodeId::of($className), true, null);

        $items = [];
        foreach ($node->props as $property) {
            $meta = new FileMeta($scope->getFile(), $property->getStartLine(), 1);
            $declared = new PropertyNode(PropertyNodeId::of($className, $property->name->toString()), true, $meta);

            $items[] = $declared;
            $items[] = new PropertyEdge($owner, $declared, $meta);
            array_push($items, ...AttributeProcessor::process($node->attrGroups, $declared, $scope));

            foreach (TypeResolver::references($node->type, $scope->getFile()) as $type) {
                $items[] = new TypePropertyEdge($declared, $type->node, $type->meta);
            }
        }

        return $items;
    }
}
