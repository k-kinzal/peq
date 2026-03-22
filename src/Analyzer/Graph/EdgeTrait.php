<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Trait for common Edge functionality.
 */
trait EdgeTrait
{
    public readonly Node $fromNode;

    public readonly Node $toNode;

    public readonly FileMeta $meta;

    /**
     * @return NodeId<Node>
     */
    public function from(): NodeId
    {
        return $this->fromNode->id();
    }

    /**
     * @return NodeId<Node>
     */
    public function to(): NodeId
    {
        return $this->toNode->id();
    }

    public function meta(): FileMeta
    {
        return $this->meta;
    }
}
