<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;

/**
 * Represents an interface implementation declaration relationship.
 */
final class DeclarationImplementsEdge extends AuthoredEdge
{
    /**
     * @param ClassNode|EnumNode $from The node the relation starts at
     * @param GraphInterfaceNode $to   The node the relation points at
     * @param FileMeta           $meta Where in the source code the relation is written
     */
    public function __construct(
        ClassNode|EnumNode $from,
        GraphInterfaceNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::DeclarationImplements
     */
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationImplements;
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
