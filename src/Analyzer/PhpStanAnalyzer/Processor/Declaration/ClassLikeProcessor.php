<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Declaration\DeclarationReader;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\ImplementsEdge;
use App\Analyzer\Graph\Edge\Declaration\TraitUseEdge;
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

/**
 * Records a class-like declaration and what it is built from.
 *
 * A class, interface, trait or enum brings its own node into the graph together with
 * what it extends, implements and uses, and the attributes written on it. These are
 * the relations that make an inheritance chain walkable in both directions.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class ClassLikeProcessor
{
    /**
     * Records the declaration and everything it takes on.
     *
     * An anonymous class has no name to key a node by, so nothing is recorded for it.
     *
     * @param ClassLike $node  The syntax node met during analysis
     * @param Scope     $scope The analyser scope it was written in
     *
     * @return list<AttributeEdge|ClassNode|EnumNode|ExtendsEdge|GraphInterfaceNode|ImplementsEdge|TraitNode|TraitUseEdge> The relations it describes
     */
    public static function process(ClassLike $node, Scope $scope): array
    {
        if ($node->namespacedName === null) {
            return [];
        }

        $meta = new FileMeta($scope->getFile(), $node->getStartLine(), 1);
        $declared = self::declaredNode(
            $node,
            $node->namespacedName->toString(),
            $meta,
            DeclarationReader::forClassLike($node, AttributeProcessor::usages($node->attrGroups, $scope)),
        );
        if ($declared === null) {
            return [];
        }

        return [
            $declared,
            ...AttributeProcessor::process($node->attrGroups, $declared, $scope),
            ...self::inheritance($node, $declared, $meta),
        ];
    }

    /**
     * Builds the node for the declaration itself.
     *
     * @param ClassLike         $node        The syntax node met during analysis
     * @param string            $className   The fully qualified name it declares
     * @param FileMeta          $meta        Where the declaration is written
     * @param SymbolDeclaration $declaration What the declaration says about itself
     *
     * @return null|ClassNode|EnumNode|GraphInterfaceNode|TraitNode The declared node, or null for a kind with no node
     */
    public static function declaredNode(ClassLike $node, string $className, FileMeta $meta, SymbolDeclaration $declaration): ClassNode|EnumNode|GraphInterfaceNode|TraitNode|null
    {
        return match (true) {
            $node instanceof Class_ => new ClassNode(ClassNodeId::of($className), true, $meta, $declaration),
            $node instanceof Interface_ => new GraphInterfaceNode(InterfaceNodeId::of($className), true, $meta, $declaration),
            $node instanceof Trait_ => new TraitNode(TraitNodeId::of($className), true, $meta, $declaration),
            $node instanceof Enum_ => new EnumNode(EnumNodeId::of($className), true, $meta, $declaration),
            default => null,
        };
    }

    /**
     * Records what a declaration extends, implements and uses.
     *
     * Which of those a declaration may have is decided by its kind, and PHP's own
     * rules are what the branches here say: only a class extends a class, only an
     * interface extends several, an enum implements but never extends, and every
     * class-like except an interface may use a trait — an enum included, which the
     * previous shape of this code asserted against.
     *
     * @param ClassLike                                       $node     The syntax node met during analysis
     * @param ClassNode|EnumNode|GraphInterfaceNode|TraitNode $declared The node of the declaration itself
     * @param FileMeta                                        $meta     Where the declaration is written
     *
     * @return list<ExtendsEdge|ImplementsEdge|TraitUseEdge> The inheritance relations
     */
    public static function inheritance(ClassLike $node, ClassNode|EnumNode|GraphInterfaceNode|TraitNode $declared, FileMeta $meta): array
    {
        $items = [];

        if ($node instanceof Class_ && $node->extends !== null && $declared instanceof ClassNode) {
            $items[] = new ExtendsEdge($declared, new ClassNode(ClassNodeId::of($node->extends->toString()), false, null), $meta);
        }

        if ($node instanceof Interface_ && $declared instanceof GraphInterfaceNode) {
            foreach ($node->extends as $parent) {
                $items[] = new ExtendsEdge($declared, new GraphInterfaceNode(InterfaceNodeId::of($parent->toString()), false, null), $meta);
            }
        }

        if (($node instanceof Class_ || $node instanceof Enum_) && ($declared instanceof ClassNode || $declared instanceof EnumNode)) {
            foreach ($node->implements as $contract) {
                $items[] = new ImplementsEdge($declared, new GraphInterfaceNode(InterfaceNodeId::of($contract->toString()), false, null), $meta);
            }
        }

        if (!$declared instanceof GraphInterfaceNode) {
            foreach ($node->getTraitUses() as $traitUse) {
                foreach ($traitUse->traits as $trait) {
                    $items[] = new TraitUseEdge($declared, new TraitNode(TraitNodeId::of($trait->toString()), false, null), $meta);
                }
            }
        }

        return $items;
    }
}
