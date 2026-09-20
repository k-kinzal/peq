<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use Override;

/**
 * Represents an attribute usage relationship.
 */
final readonly class AttributeEdge extends AuthoredEdge
{
    /**
     * @param Node      $from The node the relation starts at
     * @param ClassNode $to   The node the relation points at
     * @param FileMeta  $meta Where in the source code the relation is written
     */
    public function __construct(
        Node $from,
        ClassNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::Attribute
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::Attribute;
    }

    /**
     * Returns this relation read in the opposite direction.
     *
     * @return Edge A UsedByEdge carrying this edge, which inverts back into it
     */
    #[Override]
    public function invert(): Edge
    {
        return new UsedByEdge($this);
    }
}
