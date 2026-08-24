<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationConstantEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Analyser\Scope;

/**
 * Records the constants a class-like declares.
 *
 * One `const A = 1, B = 2;` statement declares several constants, and the attributes
 * written on the statement apply to every one of them, so each declared constant
 * carries the same attribute relations.
 *
 * @visibility parent
 */
final class ClassConstProcessor
{
    /**
     * Records every constant one statement declares.
     *
     * @param ClassConst $node  The syntax node met during analysis
     * @param Scope      $scope The analyser scope it was written in
     *
     * @return list<AttributeEdge|ConstantNode|DeclarationConstantEdge> The relations it describes
     */
    public static function process(ClassConst $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $className = $classReflection->getName();
        $owner = new ClassNode(ClassNodeId::of($className), true, null);
        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);

        $items = [];
        foreach ($node->consts as $constant) {
            $declared = new ConstantNode(ConstantNodeId::of($className, $constant->name->toString()), true, $meta);

            $items[] = $declared;
            $items[] = new DeclarationConstantEdge($owner, $declared, $meta);
            array_push($items, ...AttributeProcessor::process($node->attrGroups, $declared, $scope));
        }

        return $items;
    }
}
