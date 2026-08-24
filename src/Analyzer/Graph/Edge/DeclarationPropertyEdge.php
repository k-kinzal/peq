<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;

/**
 * Represents a property declaration relationship within a class/trait/enum.
 */
final class DeclarationPropertyEdge extends AuthoredEdge
{
    /**
     * @param ClassNode|TraitNode $from The node the relation starts at
     * @param PropertyNode        $to   The node the relation points at
     * @param FileMeta            $meta Where in the source code the relation is written
     */
    public function __construct(
        ClassNode|TraitNode $from,
        PropertyNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::DeclarationProperty
     */
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationProperty;
    }

    /**
     * Returns this relation read in the opposite direction.
     *
     * @return Edge A DeclaredInEdge carrying this edge, which inverts back into it
     */
    public function invert(): Edge
    {
        return new DeclaredInEdge($this);
    }
}
