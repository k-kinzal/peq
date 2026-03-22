<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;

final class StubEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        Node $from,
        Node $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::UsedBy;
    }

    public function invert(): Edge
    {
        return new StubEdge($this->toNode, $this->fromNode, $this->meta);
    }
}
