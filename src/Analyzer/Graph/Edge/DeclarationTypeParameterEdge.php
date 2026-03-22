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
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\TraitNode;

/**
 * Represents a parameter type declaration relationship.
 */
final readonly class DeclarationTypeParameterEdge implements Edge
{
    use EdgeTrait;

    public function __construct(
        FunctionNode|MethodNode $from,
        BuiltinNode|ClassNode|EnumNode|GraphInterfaceNode|TraitNode $to,
        FileMeta $meta,
    ) {
        $this->fromNode = $from;
        $this->toNode = $to;
        $this->meta = $meta;
    }

    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationTypeParameter;
    }

    public function invert(): Edge
    {
        return new DeclaredInEdge(from: $this->toNode, to: $this->fromNode, meta: $this->meta);
    }
}
