<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\TraitNode;
use Override;

/**
 * Represents a trait use declaration relationship.
 */
final readonly class TraitUseEdge extends AuthoredEdge
{
    /**
     * @param ClassNode|EnumNode|TraitNode $from The node the relation starts at
     * @param TraitNode                    $to   The node the relation points at
     * @param FileMeta                     $meta Where in the source code the relation is written
     */
    public function __construct(
        ClassNode|EnumNode|TraitNode $from,
        TraitNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::DeclarationTraitUse
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationTraitUse;
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
