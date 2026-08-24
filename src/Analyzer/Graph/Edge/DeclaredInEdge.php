<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\InverseEdge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;

/**
 * The opposite reading of a declaration relation: "is declared in".
 *
 * Where a declaration edge says "this class declares that method", this edge says
 * "that method is declared in this class". It is derived from the declaration edge
 * it carries and inverts straight back into it, so the specific declaration kind —
 * extends, implements, trait use, method, property, constant, enum case or a type
 * position — survives the reverse direction.
 */
final class DeclaredInEdge implements InverseEdge
{
    /**
     * @param Edge $declaration The declaration relation this edge is the opposite reading of
     */
    public function __construct(
        private readonly Edge $declaration,
    ) {}

    /**
     * Returns the declared node, which the declaration edge points at.
     *
     * @return NodeId<Node> The declared node identifier
     */
    public function from(): NodeId
    {
        return $this->declaration->to();
    }

    /**
     * Returns the declaring node, which the declaration edge starts at.
     *
     * @return NodeId<Node> The declaring node identifier
     */
    public function to(): NodeId
    {
        return $this->declaration->from();
    }

    /**
     * Returns the kind that marks this edge as a reverse declaration relation.
     *
     * @return EdgeKind Always EdgeKind::DeclaredIn
     */
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclaredIn;
    }

    /**
     * Returns where the underlying declaration is written in the source code.
     *
     * @return FileMeta The location of the declaration this edge reverses
     */
    public function meta(): FileMeta
    {
        return $this->declaration->meta();
    }

    /**
     * Returns the declaration relation this edge was derived from.
     *
     * @return Edge The original declaration edge, with its original kind intact
     */
    public function invert(): Edge
    {
        return $this->declaration;
    }
}
