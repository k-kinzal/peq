<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor\Declaration;

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
    public static function process(array $attributeGroups, Node $owner, Scope $scope): array
    {
        $items = [];
        foreach ($attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $items[] = new AttributeEdge(
                    $owner,
                    new ClassNode(ClassNodeId::of($scope->resolveName($attribute->name)), false, null),
                    new FileMeta($scope->getFile(), $attribute->getStartLine(), 1),
                );
            }
        }

        return $items;
    }
}
