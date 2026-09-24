<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Declaration;

use App\Analyzer\Declaration\WrittenAttribute;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use PhpParser\Node\AttributeGroup;
use PHPStan\Analyser\Scope;

/**
 * Records the attributes written on a declaration.
 *
 * An attribute names a class, and writing it is a dependency on that class in
 * exactly the way instantiating it would be. Every kind of declaration can carry
 * attributes, so reading them belongs here rather than being repeated by each
 * processor that handles a declaration.
 *
 * @visibility App\Analyzer\PhpStanAnalyzer
 */
final class AttributeProcessor
{
    /**
     * Records the attributes of one declaration.
     *
     * Each relation is positioned at the attribute itself rather than at the
     * declaration it is written on, so a declaration carrying several attributes
     * reports each of them where it stands.
     *
     * @param array<AttributeGroup> $attributeGroups The attribute groups written on the declaration
     * @param Node                  $owner           The declaration they are written on
     * @param Scope                 $scope           The analyser scope, used to resolve the written names
     *
     * @return list<AttributeEdge> One relation per attribute
     */
    public static function process(array $attributeGroups, Node $owner, Scope $scope, ?string $parameter = null): array
    {
        $items = [];
        foreach ($attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $items[] = new AttributeEdge(
                    $owner,
                    new ClassNode(ClassNodeId::of($scope->resolveName($attribute->name)), false, null),
                    new FileMeta($scope->getFile(), $attribute->getStartLine(), 1, $attribute->getStartFilePos()),
                    WrittenAttribute::usage($attribute, $scope->resolveName($attribute->name))->arguments,
                    $parameter,
                );
            }
        }

        return $items;
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
     * @param Scope                 $scope           The analyser scope, used to resolve the written names
     *
     * @return list<AttributeUsage> The attributes, in source order
     */
    public static function usages(array $attributeGroups, Scope $scope): array
    {
        $usages = [];
        foreach ($attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $usages[] = WrittenAttribute::usage($attribute, $scope->resolveName($attribute->name));
            }
        }

        return $usages;
    }
}
