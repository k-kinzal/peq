<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Emitter;

use App\Analyzer\Declaration\DeclarationReader;
use App\Analyzer\ExperimentAnalyzer\AnalysisScope;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Declaration\PropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeParameterEdge;
use App\Analyzer\Graph\Edge\Declaration\TypePropertyEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeReturnEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

/**
 * Records a method or a function declaration and the types its signature commits to.
 *
 * A declaration brings its own symbol, the relation to whatever declares it, and
 * every class-like its signature names: the type of each parameter and the return
 * type. Those are dependencies the declaration commits to in public, which is what
 * makes them worth recording separately from whatever its body happens to do.
 *
 * A closure and an arrow function are callable too, but neither has a name to record
 * a symbol under, so nothing is recorded for them; what their bodies reach is
 * recorded against the method or function they are written inside.
 *
 * @visibility App\Analyzer\ExperimentAnalyzer
 */
final class CallableEmitter
{
    /**
     * Records a method declaration and its signature.
     *
     * @param ClassMethod   $node      The declaration met while walking
     * @param AnalysisScope $scope     Where in the sources it is written, standing in the declaring class-like
     * @param NodeKind      $ownerKind The kind of the class-like that declares it
     *
     * @return list<Edge|Node> The symbol it declares and the relations it writes
     */
    public static function method(ClassMethod $node, AnalysisScope $scope, NodeKind $ownerKind): array
    {
        $className = $scope->className;
        if ($className === null) {
            return [];
        }
        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);
        $declared = new MethodNode(
            MethodNodeId::of($className, $node->name->toString()),
            true,
            $meta,
            DeclarationReader::forCallable($node, DeclarationEmitter::attributeUsages($node->getAttrGroups(), $scope)),
        );

        return [
            new MethodEdge(DeclarationEmitter::ownerNode($ownerKind, $className), $declared, $meta),
            $declared,
            ...self::signature($node, $declared, $scope, $meta),
        ];
    }

    /**
     * Records a function declaration and its signature.
     *
     * @param Function_     $node  The declaration met while walking
     * @param AnalysisScope $scope Where in the sources it is written
     *
     * @return list<Edge|Node> The symbol it declares and the relations it writes
     */
    public static function globalFunction(Function_ $node, AnalysisScope $scope): array
    {
        if ($node->namespacedName === null) {
            return [];
        }
        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);
        $declared = new FunctionNode(
            FunctionNodeId::of($node->namespacedName->toString()),
            true,
            $meta,
            DeclarationReader::forCallable($node, DeclarationEmitter::attributeUsages($node->getAttrGroups(), $scope)),
        );

        return [$declared, ...self::signature($node, $declared, $scope, $meta)];
    }

    /**
     * Records the attributes and the declared types of a signature.
     *
     * A parameter's attributes are recorded against the callable rather than against
     * the parameter, because a parameter is not a symbol of its own: the graph records
     * what a declaration depends on, and a parameter attribute is one of those things.
     *
     * @param FunctionLike            $node     The declaration met while walking
     * @param FunctionNode|MethodNode $declared The symbol of the declaration itself
     * @param AnalysisScope           $scope    Where in the sources it is written
     * @param FileMeta                $meta     Where the declaration is written
     *
     * @return list<AttributeEdge|TypeParameterEdge|TypeReturnEdge> The relations its signature writes
     */
    public static function signature(FunctionLike $node, FunctionNode|MethodNode $declared, AnalysisScope $scope, FileMeta $meta): array
    {
        $items = DeclarationEmitter::attributes($node->getAttrGroups(), $declared, $scope);

        foreach (TypeMention::of($node->getReturnType(), $scope->file) as $type) {
            $items[] = new TypeReturnEdge($declared, $type->node, $type->meta);
        }

        foreach ($node->getParams() as $param) {
            array_push($items, ...DeclarationEmitter::attributes($param->attrGroups, $declared, $scope, $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : null));

            foreach (TypeMention::of($param->type, $scope->file) as $type) {
                $items[] = new TypeParameterEdge($declared, $type->node, $type->meta);
            }
        }

        return $items;
    }

    /**
     * Records the property a promoted constructor parameter declares.
     *
     * A parameter is promoted by carrying a visibility, and a promoted parameter
     * declares a property: it produces the same relations a written property would.
     * A parameter that carries no visibility declares nothing, and neither does one
     * written outside a class-like.
     *
     * @param Param         $node  The parameter met while walking
     * @param AnalysisScope $scope Where in the sources it is written
     *
     * @return list<Edge|Node> The symbol it declares and the relations it writes
     */
    public static function promotedProperty(Param $node, AnalysisScope $scope): array
    {
        $className = $scope->className;
        if ($className === null
            || ($node->flags & Modifiers::VISIBILITY_MASK) === 0
            || !$node->var instanceof Variable
            || !is_string($node->var->name)
        ) {
            return [];
        }

        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);
        $declared = new PropertyNode(
            PropertyNodeId::of($className, $node->var->name),
            true,
            $meta,
            DeclarationReader::forPromotedProperty($node, DeclarationEmitter::attributeUsages($node->attrGroups, $scope)),
        );

        $items = [
            $declared,
            new PropertyEdge(new ClassNode(ClassNodeId::of($className), true, null), $declared, $meta),
            ...DeclarationEmitter::attributes($node->attrGroups, $declared, $scope),
        ];

        foreach (TypeMention::of($node->type, $scope->file) as $type) {
            $items[] = new TypePropertyEdge($declared, $type->node, $type->meta);
        }

        return $items;
    }
}
