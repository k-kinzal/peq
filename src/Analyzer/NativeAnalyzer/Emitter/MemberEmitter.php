<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\ConstantEdge;
use App\Analyzer\Graph\Edge\Declaration\EnumCaseEdge;
use App\Analyzer\Graph\Edge\Declaration\PropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;

/**
 * Records the state a class-like declares: its properties, constants and enum cases.
 *
 * Each of these is a symbol code can name directly, so each becomes a symbol of its
 * own related to the declaration that holds it. One statement may declare several of
 * them — `public $a, $b;` declares two properties — and what is written once for the
 * statement, its attributes and its type, belongs to every symbol it declares.
 *
 * @visibility App\Analyzer\NativeAnalyzer
 */
final class MemberEmitter
{
    /**
     * Records every property one statement declares.
     *
     * @param Property      $node  The statement met while walking
     * @param AnalysisScope $scope Where in the sources it is written, standing in the declaring class-like
     *
     * @return list<Edge|Node> The symbols it declares and the relations it writes
     */
    public static function properties(Property $node, AnalysisScope $scope): array
    {
        $className = $scope->className;
        if ($className === null) {
            return [];
        }
        $owner = new ClassNode(ClassNodeId::of($className), true, null);

        $items = [];
        foreach ($node->props as $property) {
            $meta = new FileMeta($scope->file, $property->getStartLine(), 1);
            $declared = new PropertyNode(PropertyNodeId::of($className, $property->name->toString()), true, $meta);

            $items[] = $declared;
            $items[] = new PropertyEdge($owner, $declared, $meta);
            array_push($items, ...DeclarationEmitter::attributes($node->attrGroups, $declared, $scope));

            foreach (TypeMention::of($node->type, $scope->file) as $type) {
                $items[] = new TypePropertyEdge($declared, $type->node, $type->meta);
            }
        }

        return $items;
    }

    /**
     * Records every constant one statement declares.
     *
     * @param ClassConst    $node  The statement met while walking
     * @param AnalysisScope $scope Where in the sources it is written, standing in the declaring class-like
     *
     * @return list<Edge|Node> The symbols it declares and the relations it writes
     */
    public static function constants(ClassConst $node, AnalysisScope $scope): array
    {
        $className = $scope->className;
        if ($className === null) {
            return [];
        }
        $owner = new ClassNode(ClassNodeId::of($className), true, null);
        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);

        $items = [];
        foreach ($node->consts as $constant) {
            $declared = new ConstantNode(ConstantNodeId::of($className, $constant->name->toString()), true, $meta);

            $items[] = $declared;
            $items[] = new ConstantEdge($owner, $declared, $meta);
            array_push($items, ...DeclarationEmitter::attributes($node->attrGroups, $declared, $scope));
        }

        return $items;
    }

    /**
     * Records the case an enum declares.
     *
     * @param EnumCase      $node  The case met while walking
     * @param AnalysisScope $scope Where in the sources it is written, standing in the declaring enum
     *
     * @return list<Edge|Node> The symbol it declares and the relations it writes
     */
    public static function enumCase(EnumCase $node, AnalysisScope $scope): array
    {
        $className = $scope->className;
        if ($className === null) {
            return [];
        }
        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);
        $declared = new EnumCaseNode(EnumCaseNodeId::of($className, $node->name->toString()), true, $meta);

        return [
            $declared,
            new EnumCaseEdge(new EnumNode(EnumNodeId::of($className), true, null), $declared, $meta),
            ...DeclarationEmitter::attributes($node->attrGroups, $declared, $scope),
        ];
    }
}
