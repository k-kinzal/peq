<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationConstantEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Analyser\Scope;

final class ClassConstProcessor
{
    /**
     * @return array<AttributeEdge|ConstantNode|DeclarationConstantEdge>
     */
    public static function process(ClassConst $node, Scope $scope): array
    {
        $items = [];
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return [];
        }

        $className = $classReflection->getName();
        $classNodeId = new ClassNodeId(
            self::getNamespace($className),
            self::getShortName($className)
        );
        $classNode = new ClassNode($classNodeId, true, null);

        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);

        foreach ($node->consts as $const) {
            $constName = $const->name->toString();
            $constNodeId = new ConstantNodeId(
                self::getNamespace($className),
                self::getShortName($className),
                $constName
            );

            $constNode = new ConstantNode($constNodeId, true, $meta);
            $items[] = $constNode;

            // Edge from Class to Constant
            $edge = new DeclarationConstantEdge($classNode, $constNode, $meta);
            $items[] = $edge;
        }

        // Attributes
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                // Attribute on Constant
                $attrClassName = $scope->resolveName($attr->name); // Resolve attribute name
                $attrClassId = new ClassNodeId(
                    self::getNamespace($attrClassName),
                    self::getShortName($attrClassName)
                );
                $attrClassNode = new ClassNode($attrClassId, false, null);

                foreach ($items as $item) {
                    if ($item instanceof ConstantNode) {
                        $edge = new AttributeEdge($item, $attrClassNode, $meta);
                        $items[] = $edge;
                    }
                }
            }
        }

        return $items;
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
