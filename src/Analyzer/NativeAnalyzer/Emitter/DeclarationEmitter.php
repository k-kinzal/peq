<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Declaration\DeclarationReader;
use App\Analyzer\Declaration\WrittenAttribute;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\ImplementsEdge;
use App\Analyzer\Graph\Edge\Declaration\TraitUseEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;

/**
 * Records a class-like declaration and what it is built from.
 *
 * A class, interface, trait or enum brings its own symbol into the graph together
 * with what it extends, implements and uses, and the attributes written on it. These
 * are the relations that make an inheritance chain walkable in both directions, and
 * they are read off the declaration alone — nothing about them depends on what the
 * declared members turn out to be.
 *
 * An anonymous class declares no name to record any of this against, so nothing is
 * recorded for the declaration itself; what its members declare is recorded against
 * the name analysis invents for it.
 *
 * @visibility App\Analyzer\NativeAnalyzer
 */
final class DeclarationEmitter
{
    /**
     * Records a named class-like declaration and everything it takes on.
     *
     * @param ClassLike     $node  The declaration met while walking
     * @param NodeKind      $kind  Which of the four kinds of declaration it is
     * @param string        $name  Its fully qualified name
     * @param AnalysisScope $scope Where in the sources it is written
     *
     * @return list<Edge|Node> The symbol it declares and the relations it writes
     */
    public static function emit(ClassLike $node, NodeKind $kind, string $name, AnalysisScope $scope): array
    {
        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);
        $declared = self::ownerNode(
            $kind,
            $name,
            $meta,
            DeclarationReader::forClassLike($node, self::attributeUsages($node->attrGroups, $scope)),
        );

        return [
            $declared,
            ...self::attributes($node->attrGroups, $declared, $scope),
            ...self::inheritance($node, $declared, $meta),
        ];
    }

    /**
     * Builds the symbol that stands for a class-like of the given kind.
     *
     * @param NodeKind               $kind        The kind of declaration
     * @param string                 $name        Its fully qualified name
     * @param null|FileMeta          $meta        Where it is written, or null when the symbol is only being referred to
     * @param null|SymbolDeclaration $declaration What its declaration says, or null when it is only being referred to
     *
     * @return ClassNode|EnumNode|GraphInterfaceNode|TraitNode The symbol for that declaration
     */
    public static function ownerNode(NodeKind $kind, string $name, ?FileMeta $meta = null, ?SymbolDeclaration $declaration = null): ClassNode|EnumNode|GraphInterfaceNode|TraitNode
    {
        return match ($kind) {
            NodeKind::Interface => new GraphInterfaceNode(InterfaceNodeId::of($name), true, $meta, $declaration),
            NodeKind::Trait => new TraitNode(TraitNodeId::of($name), true, $meta, $declaration),
            NodeKind::Enum => new EnumNode(EnumNodeId::of($name), true, $meta, $declaration),
            NodeKind::Klass,
            NodeKind::Constant,
            NodeKind::EnumCase,
            NodeKind::Function,
            NodeKind::Method,
            NodeKind::Property,
            NodeKind::Builtin,
            NodeKind::Unknown => new ClassNode(ClassNodeId::of($name), true, $meta, $declaration),
        };
    }

    /**
     * Reads the attributes written on a declaration, under the names they resolve to.
     *
     * The same attributes are read twice for one declaration: once as relations to
     * the classes they name, and once as facts about the declaration that carries
     * them. They are different answers to different questions — what breaks if the
     * attribute class changes, and which declarations are marked with it — so both
     * are recorded rather than one being derived from the other at query time.
     *
     * @param array<AttributeGroup> $attributeGroups The attribute groups written on the declaration
     * @param AnalysisScope         $scope           Where in the sources they are written
     *
     * @return list<AttributeUsage> The attributes, in source order
     */
    public static function attributeUsages(array $attributeGroups, AnalysisScope $scope): array
    {
        $usages = [];
        foreach ($attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $usages[] = WrittenAttribute::usage($attribute, $scope->resolveName($attribute->name));
            }
        }

        return $usages;
    }

    /**
     * Records the attributes written on a declaration.
     *
     * An attribute names a class, and writing it depends on that class in exactly the
     * way instantiating it would. Every kind of declaration can carry attributes, so
     * reading them belongs here rather than being repeated by each kind.
     *
     * @param array<AttributeGroup> $attributeGroups The attribute groups written on the declaration
     * @param Node                  $owner           The declaration they are written on
     * @param AnalysisScope         $scope           Where in the sources they are written
     *
     * @return list<AttributeEdge> One relation per attribute
     */
    public static function attributes(array $attributeGroups, Node $owner, AnalysisScope $scope, ?string $parameter = null): array
    {
        $items = [];
        foreach ($attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $items[] = new AttributeEdge(
                    $owner,
                    new ClassNode(ClassNodeId::of($scope->resolveName($attribute->name)), false, null),
                    new FileMeta($scope->file, $attribute->getStartLine(), 1, $attribute->getStartFilePos()),
                    WrittenAttribute::usage($attribute, $scope->resolveName($attribute->name))->arguments,
                    $parameter,
                );
            }
        }

        return $items;
    }

    /**
     * Records what a declaration extends, implements and uses.
     *
     * Which of those a declaration may have is decided by its kind, and PHP's own
     * rules are what the branches here say: only a class extends a class, only an
     * interface extends several, an enum implements but never extends, and every
     * class-like except an interface may use a trait.
     *
     * @param ClassLike                                       $node     The declaration met while walking
     * @param ClassNode|EnumNode|GraphInterfaceNode|TraitNode $declared The symbol of the declaration itself
     * @param FileMeta                                        $meta     Where the declaration is written
     *
     * @return list<ExtendsEdge|ImplementsEdge|TraitUseEdge> The relations it takes on
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
