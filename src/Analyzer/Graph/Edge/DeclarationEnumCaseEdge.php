<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;

/**
 * Represents an enum case declaration relationship within an enum.
 */
final class DeclarationEnumCaseEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        EnumNode $from,
        EnumCaseNode $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationEnumCase;
    }

    public function invert(): Edge
    {
        return new DeclaredInEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
