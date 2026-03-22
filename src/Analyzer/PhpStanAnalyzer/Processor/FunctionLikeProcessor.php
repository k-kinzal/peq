<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeParameterEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge;
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
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;

final class FunctionLikeProcessor
{
    /**
     * @return array<AttributeEdge|DeclarationMethodEdge|DeclarationTypeParameterEdge|DeclarationTypeReturnEdge|FunctionNode|MethodNode>
     */
    public static function process(FunctionLike $node, Scope $scope): array
    {
        $items = [];
        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);

        $sourceNode = null;

        if ($node instanceof ClassMethod) {
            $classReflection = $scope->getClassReflection();
            if ($classReflection === null) {
                return [];
            }
            $className = $classReflection->getName();
            $methodName = $node->name->toString();
            $sourceNode = new MethodNode(
                new MethodNodeId(self::getNamespace($className), self::getShortName($className), $methodName),
                true,
                $meta
            );

            $namespace = self::getNamespace($className);
            $shortName = self::getShortName($className);
            $parentSourceNode = null;

            if ($classReflection->isInterface()) {
                $parentSourceNode = new GraphInterfaceNode(new InterfaceNodeId($namespace, $shortName), true, null);
            } elseif ($classReflection->isTrait()) {
                $parentSourceNode = new TraitNode(new TraitNodeId($namespace, $shortName), true, null);
            } elseif ($classReflection->isEnum()) {
                $parentSourceNode = new EnumNode(new EnumNodeId($namespace, $shortName), true, null);
            } else {
                $parentSourceNode = new ClassNode(new ClassNodeId($namespace, $shortName), true, null);
            }

            $items[] = new DeclarationMethodEdge($parentSourceNode, $sourceNode, $meta);
        } elseif ($node instanceof Function_) {
            if (!isset($node->namespacedName)) {
                return [];
            }
            $functionName = $node->namespacedName->toString();
            $sourceNode = new FunctionNode(
                new FunctionNodeId(self::getNamespace($functionName), self::getShortName($functionName)),
                true,
                $meta
            );
        }

        if ($sourceNode === null) {
            return [];
        }

        $items[] = $sourceNode;

        // Attributes on Function/Method
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $scope->resolveName($attr->name);
                $attrNode = new ClassNode(new ClassNodeId(self::getNamespace($attrName), self::getShortName($attrName)), false, null);
                $items[] = new AttributeEdge($sourceNode, $attrNode, $meta);
            }
        }

        // Return Type
        $returnType = $node->getReturnType();
        foreach (TypeResolver::resolveNames($returnType) as $typeName) {
            $name = $typeName->toString();
            if (!self::isBuiltin($name)) {
                $typeNode = new ClassNode(new ClassNodeId(self::getNamespace($name), self::getShortName($name)), false, null);
                $items[] = new DeclarationTypeReturnEdge($sourceNode, $typeNode, new FileMeta($scope->getFile(), $typeName->getStartLine(), 1));
            }
        }

        // Parameters
        foreach ($node->getParams() as $param) {
            // Attributes on Parameter
            foreach ($param->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    $attrName = $scope->resolveName($attr->name);
                    $attrNode = new ClassNode(new ClassNodeId(self::getNamespace($attrName), self::getShortName($attrName)), false, null);
                    // Attribute on Parameter
                    $items[] = new AttributeEdge($sourceNode, $attrNode, new FileMeta($scope->getFile(), $param->getStartLine(), 1));
                }
            }

            foreach (TypeResolver::resolveNames($param->type) as $typeName) {
                $name = $typeName->toString();
                if (!self::isBuiltin($name)) {
                    $typeNode = new ClassNode(new ClassNodeId(self::getNamespace($name), self::getShortName($name)), false, null);
                    $items[] = new DeclarationTypeParameterEdge($sourceNode, $typeNode, new FileMeta($scope->getFile(), $typeName->getStartLine(), 1));
                }
            }
        }

        return $items;
    }

    private static function isBuiltin(string $name): bool
    {
        return SourceResolver::isBuiltin($name);
    }

    private static function getNamespace(string $name): string
    {
        $lastSlash = strrpos($name, '\\');
        if ($lastSlash === false) {
            return '';
        }

        return substr($name, 0, $lastSlash);
    }

    private static function getShortName(string $name): string
    {
        $lastSlash = strrpos($name, '\\');
        if ($lastSlash === false) {
            return $name;
        }

        return substr($name, $lastSlash + 1);
    }
}
