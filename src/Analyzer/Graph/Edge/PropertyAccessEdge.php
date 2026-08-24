<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;

/**
 * Represents an instance property access relationship.
 */
final class PropertyAccessEdge extends AuthoredEdge
{
    /**
     * @param FunctionNode|MethodNode $from The node the relation starts at
     * @param PropertyNode            $to   The node the relation points at
     * @param FileMeta                $meta Where in the source code the relation is written
     */
    public function __construct(
        FunctionNode|MethodNode $from,
        PropertyNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::PropertyAccess
     */
    public function kind(): EdgeKind
    {
        return EdgeKind::PropertyAccess;
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
