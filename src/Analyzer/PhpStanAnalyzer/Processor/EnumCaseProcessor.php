<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationEnumCaseEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;

final class EnumCaseProcessor
{
    /**
     * @return array<AttributeEdge|DeclarationEnumCaseEdge|EnumCaseNode>
     */
    public static function process(EnumCase $node, Scope $scope): array
    {
        $items = [];
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }
        $className = $classReflection->getName();
        $caseName = $node->name->toString();

        $caseNode = new EnumCaseNode(
            new EnumCaseNodeId(self::getNamespace($className), self::getShortName($className), $caseName),
            true,
            new FileMeta($scope->getFile(), $node->getStartLine(), 1)
        );
        $items[] = $caseNode;

        $parentSourceNode = new EnumNode(new EnumNodeId(self::getNamespace($className), self::getShortName($className)), true, null);
        $items[] = new DeclarationEnumCaseEdge($parentSourceNode, $caseNode, new FileMeta($scope->getFile(), $node->getStartLine(), 1));

        // Attributes
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $scope->resolveName($attr->name);
                $attrNode = new ClassNode(new ClassNodeId(self::getNamespace($attrName), self::getShortName($attrName)), false, null);
                $items[] = new AttributeEdge($caseNode, $attrNode, new FileMeta($scope->getFile(), $attr->getStartLine(), 1));
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
