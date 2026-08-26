<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeParameterEdge;
use App\Analyzer\Graph\Edge\Declaration\TypeReturnEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;

/**
 * Records a function or method declaration and its signature.
 *
 * The declaration brings its own node, the relation to the class-like that declares
 * it, and the types written in its signature: every parameter type and the return
 * type, each of which is a dependency the signature commits to.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class FunctionLikeProcessor
{
    /**
     * Records the declaration and the types its signature commits to.
     *
     * A closure and an arrow function are function-like too, but neither has a name
     * to key a node by, so nothing is recorded for them; what their bodies reach is
     * recorded against the method or function they are written inside.
     *
     * @param FunctionLike $node  The syntax node met during analysis
     * @param Scope        $scope The analyser scope it was written in
     *
     * @return list<AttributeEdge|FunctionNode|MethodEdge|MethodNode|TypeParameterEdge|TypeReturnEdge> The relations it describes
     */
    public static function process(FunctionLike $node, Scope $scope): array
    {
        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);

        if ($node instanceof ClassMethod) {
            $classReflection = $scope->getClassReflection();
            if ($classReflection === null) {
                return [];
            }

            $declared = new MethodNode(MethodNodeId::of($classReflection->getName(), $node->name->toString()), true, $meta);

            return [
                new MethodEdge(self::declaringNode($classReflection), $declared, $meta),
                $declared,
                ...self::signature($node, $declared, $scope, $meta),
            ];
        }

        if ($node instanceof Function_ && $node->namespacedName !== null) {
            $declared = new FunctionNode(FunctionNodeId::of($node->namespacedName->toString()), true, $meta);

            return [$declared, ...self::signature($node, $declared, $scope, $meta)];
        }

        return [];
    }

    /**
     * Builds the node for the class-like a method is declared in.
     *
     * @param ClassReflection $classReflection The class-like the analyser is standing in
     *
     * @return ClassNode|EnumNode|GraphInterfaceNode|TraitNode The node of the declaring class-like
     */
    public static function declaringNode(ClassReflection $classReflection): ClassNode|EnumNode|GraphInterfaceNode|TraitNode
    {
        $name = $classReflection->getName();

        return match (true) {
            $classReflection->isInterface() => new GraphInterfaceNode(InterfaceNodeId::of($name), true, null),
            $classReflection->isTrait() => new TraitNode(TraitNodeId::of($name), true, null),
            $classReflection->isEnum() => new EnumNode(EnumNodeId::of($name), true, null),
            default => new ClassNode(ClassNodeId::of($name), true, null),
        };
    }

    /**
     * Records the attributes and the declared types of a signature.
     *
     * A parameter's attributes are attributed to the callable rather than to the
     * parameter, because a parameter is not a node of its own: the graph records what
     * a declaration depends on, and a parameter attribute is one of its dependencies.
     *
     * @param FunctionLike            $node     The declaration met during analysis
     * @param FunctionNode|MethodNode $declared The node of the declaration itself
     * @param Scope                   $scope    The analyser scope it was written in
     * @param FileMeta                $meta     Where the declaration is written
     *
     * @return list<AttributeEdge|TypeParameterEdge|TypeReturnEdge> The signature relations
     */
    public static function signature(FunctionLike $node, FunctionNode|MethodNode $declared, Scope $scope, FileMeta $meta): array
    {
        $items = AttributeProcessor::process($node->getAttrGroups(), $declared, $scope);

        foreach (TypeResolver::references($node->getReturnType(), $scope->getFile()) as $type) {
            $items[] = new TypeReturnEdge($declared, $type->node, $type->meta);
        }

        foreach ($node->getParams() as $param) {
            array_push($items, ...AttributeProcessor::process($param->attrGroups, $declared, $scope));

            foreach (TypeResolver::references($param->type, $scope->getFile()) as $type) {
                $items[] = new TypeParameterEdge($declared, $type->node, $type->meta);
            }
        }

        return $items;
    }
}
