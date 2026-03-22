<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;

/**
 * Represents a property type declaration relationship.
 */
final class DeclarationTypePropertyEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        PropertyNode $from,
        BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode|TraitNode $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationTypeProperty;
    }

    public function invert(): Edge
    {
        return new DeclaredInEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
