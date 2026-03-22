<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;

/**
 * Represents an inheritance relationship (extends) between classes or interfaces.
 */
final class DeclarationExtendsEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        ClassNode|GraphInterfaceNode $from,
        ClassNode|GraphInterfaceNode $to,
        FileMeta $meta,
    ) {
        assert($from::class === $to::class);
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationExtends;
    }

    public function invert(): Edge
    {
        return new DeclaredInEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
