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
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PHPStan\Analyser\Scope;

final class PromotedPropertyProcessor
{
    /**
     * @return array<AttributeEdge|DeclarationPropertyEdge|DeclarationTypePropertyEdge|PropertyNode>
     */
    public static function process(Param $node, Scope $scope): array
    {
        $items = [];
        // Check if it is a promoted property
        if (($node->flags & Modifiers::VISIBILITY_MASK) === 0) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }
        $className = $classReflection->getName();

        if ($node->var instanceof Variable && is_string($node->var->name)) {
            $propName = $node->var->name;
            $propNode = new PropertyNode(
                new PropertyNodeId(self::getNamespace($className), self::getShortName($className), $propName),
                true,
                new FileMeta($scope->getFile(), $node->getStartLine(), 1)
            );
            $items[] = $propNode;

            $namespace = self::getNamespace($className);
            $shortName = self::getShortName($className);
            $parentSourceNode = new ClassNode(new ClassNodeId($namespace, $shortName), true, null);

            $items[] = new DeclarationPropertyEdge($parentSourceNode, $propNode, new FileMeta($scope->getFile(), $node->getStartLine(), 1));

            // Attributes
            foreach ($node->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    $attrName = $scope->resolveName($attr->name);
                    $attrNode = new ClassNode(new ClassNodeId(self::getNamespace($attrName), self::getShortName($attrName)), false, null);
                    $items[] = new AttributeEdge($propNode, $attrNode, new FileMeta($scope->getFile(), $attr->getStartLine(), 1));
                }
            }

            // Type
            foreach (TypeResolver::resolveNames($node->type) as $typeName) {
                $name = $typeName->toString();
                if (!self::isBuiltin($name)) {
                    $typeNode = new ClassNode(new ClassNodeId(self::getNamespace($name), self::getShortName($name)), false, null);
                    $items[] = new DeclarationTypePropertyEdge($propNode, $typeNode, new FileMeta($scope->getFile(), $typeName->getStartLine(), 1));
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
