<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;

/**
 * Represents an attribute usage relationship.
 */
final class AttributeEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        Node $from,
        ClassNode $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::Attribute;
    }

    public function invert(): Edge
    {
        return new UsedByEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
