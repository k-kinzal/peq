<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use Override;

/**
 * Represents a property type declaration relationship.
 */
final readonly class TypePropertyEdge extends AuthoredEdge
{
    /**
     * @param PropertyNode                                                $from The node the relation starts at
     * @param BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode|TraitNode $to   The node the relation points at
     * @param FileMeta                                                    $meta Where in the source code the relation is written
     */
    public function __construct(
        PropertyNode $from,
        BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode|TraitNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::DeclarationTypeProperty
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationTypeProperty;
    }

    /**
     * Returns this relation read in the opposite direction.
     *
     * @return Edge A DeclaredInEdge carrying this edge, which inverts back into it
     */
    #[Override]
    public function invert(): Edge
    {
        return new DeclaredInEdge($this);
    }
}
