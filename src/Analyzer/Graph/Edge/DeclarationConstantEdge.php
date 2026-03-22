<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;

/**
 * Represents a constant declaration relationship within a class/interface.
 */
final readonly class DeclarationConstantEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        ClassNode|EnumNode|GraphInterfaceNode $from,
        ConstantNode $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationConstant;
    }

    public function invert(): Edge
    {
        return new DeclaredInEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
