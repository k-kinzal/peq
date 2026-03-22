<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;

final class PropertyProcessor
{
    /**
     * @return array<AttributeEdge|DeclarationPropertyEdge|DeclarationTypePropertyEdge|PropertyNode>
     */
    public static function process(Property $node, Scope $scope): array
    {
        $items = [];
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }
        $className = $classReflection->getName();

        // Property statement can have multiple properties: public $a, $b;
        foreach ($node->props as $prop) {
            $propName = $prop->name->toString();
            $propNode = new PropertyNode(
                new PropertyNodeId(self::getNamespace($className), self::getShortName($className), $propName),
                true,
                new FileMeta($scope->getFile(), $prop->getStartLine(), 1)
            );
            $items[] = $propNode;

            $namespace = self::getNamespace($className);
            $shortName = self::getShortName($className);
            $parentSourceNode = null;

            if ($classReflection->isTrait()) {
                $parentSourceNode = new TraitNode(new TraitNodeId($namespace, $shortName), true, null);
            } else {
                $parentSourceNode = new ClassNode(new ClassNodeId($namespace, $shortName), true, null);
            }

            $items[] = new DeclarationPropertyEdge($parentSourceNode, $propNode, new FileMeta($scope->getFile(), $prop->getStartLine(), 1));

            // Attributes
            foreach ($node->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    $attrName = $scope->resolveName($attr->name);
                    $attrNode = new ClassNode(new ClassNodeId(self::getNamespace($attrName), self::getShortName($attrName)), false, null);
                    $items[] = new AttributeEdge($propNode, $attrNode, new FileMeta($scope->getFile(), $attr->getStartLine(), 1));
                }
            }

            // Type
            if ($node->type instanceof Name) {
                $typeName = $node->type->toString();
                if (!self::isBuiltin($typeName)) {
                    $typeNode = new ClassNode(new ClassNodeId(self::getNamespace($typeName), self::getShortName($typeName)), false, null);
                    $items[] = new DeclarationTypePropertyEdge($propNode, $typeNode, new FileMeta($scope->getFile(), $node->type->getStartLine(), 1));
                }
            }
        }

        return $items;
    }

    private static function isBuiltin(string $name): bool
    {
        return in_array(strtolower($name), ['self', 'static', 'parent', 'int', 'string', 'float', 'bool', 'array', 'iterable', 'callable', 'void', 'object', 'mixed', 'null', 'false', 'true'], true);
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
