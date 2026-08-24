<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;

/**
 * Represents a static method call relationship.
 */
final class StaticCallEdge extends AuthoredEdge
{
    /**
     * @param FunctionNode|MethodNode $from The node the relation starts at
     * @param MethodNode              $to   The node the relation points at
     * @param FileMeta                $meta Where in the source code the relation is written
     */
    public function __construct(
        FunctionNode|MethodNode $from,
        MethodNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::StaticCall
     */
    public function kind(): EdgeKind
    {
        return EdgeKind::StaticCall;
    }

    /**
     * Returns this relation read in the opposite direction.
     *
     * @return Edge A UsedByEdge carrying this edge, which inverts back into it
     */
    public function invert(): Edge
    {
        return new UsedByEdge($this);
    }
}
