<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeKind;

final class StubNode implements Node
{
    /**
     * @param NodeId<Node> $id
     */
    public function __construct(
        public NodeId $id,
    ) {}

    public function id(): NodeId
    {
        return $this->id;
    }

    public function kind(): NodeKind
    {
        return NodeKind::Unknown;
    }

    public function resolved(): bool
    {
        return true;
    }

    public function meta(): ?FileMeta
    {
        return null;
    }
}
