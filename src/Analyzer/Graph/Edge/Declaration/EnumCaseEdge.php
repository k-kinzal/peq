<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;

/**
 * Represents an enum case declaration relationship within an enum.
 */
final class EnumCaseEdge extends AuthoredEdge
{
    /**
     * @param EnumNode     $from The node the relation starts at
     * @param EnumCaseNode $to   The node the relation points at
     * @param FileMeta     $meta Where in the source code the relation is written
     */
    public function __construct(
        EnumNode $from,
        EnumCaseNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::DeclarationEnumCase
     */
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationEnumCase;
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
