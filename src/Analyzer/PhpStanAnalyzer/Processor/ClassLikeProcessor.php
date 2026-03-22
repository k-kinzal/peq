<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge\AttributeEdge;
use App\Analyzer\Graph\Edge\DeclarationExtendsEdge;
use App\Analyzer\Graph\Edge\DeclarationImplementsEdge;
use App\Analyzer\Graph\Edge\DeclarationTraitUseEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Scope;

final class ClassLikeProcessor
{
    /**
     * @return array<AttributeEdge|ClassNode|DeclarationExtendsEdge|DeclarationImplementsEdge|DeclarationTraitUseEdge|EnumNode|GraphInterfaceNode|TraitNode>
     */
    public static function process(ClassLike $node, Scope $scope): array
    {
        if (!isset($node->namespacedName)) {
            return [];
        }

        $items = [];
        $className = $node->namespacedName->toString();
        $namespace = self::getNamespace($className);
        $shortName = self::getShortName($className);
        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);

        $classNode = null;

        if ($node instanceof Class_) {
            $classNode = new ClassNode(new ClassNodeId($namespace, $shortName), true, $meta);
        } elseif ($node instanceof Interface_) {
            $classNode = new GraphInterfaceNode(new InterfaceNodeId($namespace, $shortName), true, $meta);
        } elseif ($node instanceof Trait_) {
            $classNode = new TraitNode(new TraitNodeId($namespace, $shortName), true, $meta);
        } elseif ($node instanceof Enum_) {
            $classNode = new EnumNode(new EnumNodeId($namespace, $shortName), true, $meta);
        }

        if ($classNode === null) {
            return [];
        }

        $items[] = $classNode;

        // Attributes
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $scope->resolveName($attr->name);
                $attrNode = new ClassNode(new ClassNodeId(self::getNamespace($attrName), self::getShortName($attrName)), false, null);
                $items[] = new AttributeEdge($classNode, $attrNode, $meta);
            }
        }

        // Extends
        if ($node instanceof Class_ && $node->extends !== null) {
            $parentName = $node->extends->toString();
            $parentNode = new ClassNode(new ClassNodeId(self::getNamespace($parentName), self::getShortName($parentName)), false, null);
            assert($classNode instanceof ClassNode);
            $items[] = new DeclarationExtendsEdge($classNode, $parentNode, $meta);
        }

        if ($node instanceof Interface_) {
            foreach ($node->extends as $extends) {
                $parentName = $extends->toString();
                $parentNode = new ClassNode(new ClassNodeId(self::getNamespace($parentName), self::getShortName($parentName)), false, null);
                assert($classNode instanceof GraphInterfaceNode);
                $items[] = new DeclarationExtendsEdge($classNode, $parentNode, $meta);
            }
        }

        // Implements
        if ($node instanceof Class_ || $node instanceof Enum_) {
            foreach ($node->implements as $implement) {
                $interfaceName = $implement->toString();
                $interfaceNode = new GraphInterfaceNode(new InterfaceNodeId(self::getNamespace($interfaceName), self::getShortName($interfaceName)), false, null);
                assert($classNode instanceof ClassNode || $classNode instanceof EnumNode);
                $items[] = new DeclarationImplementsEdge($classNode, $interfaceNode, $meta);
            }
        }

        // Trait Uses
        foreach ($node->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $traitName = $trait->toString();
                $traitNode = new TraitNode(new TraitNodeId(self::getNamespace($traitName), self::getShortName($traitName)), false, null);
                assert($classNode instanceof ClassNode || $classNode instanceof TraitNode);
                $items[] = new DeclarationTraitUseEdge($classNode, $traitNode, $meta);
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
