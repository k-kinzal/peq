<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;

/**
 * Represents a caught exception relationship.
 */
final readonly class CatchEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        FunctionNode|MethodNode $from,
        ClassNode|GraphInterfaceNode $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::Catch;
    }

    public function invert(): Edge
    {
        return new UsedByEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
