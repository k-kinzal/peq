<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;

/**
 * Represents a reverse declaration relationship (e.g., method declared in class).
 */
final readonly class DeclaredInEdge implements Edge
{
    use EdgeTrait;

    /**
     * @param Node     $from Source node
     * @param Node     $to   Target node
     * @param FileMeta $meta Metadata about where this relationship is defined in source code
     */
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
        return EdgeKind::DeclaredIn;
    }

    public function invert(): Edge
    {
        $from = $this->fromNode;
        $to = $this->toNode;

        if ($from instanceof MethodNode) {
            assert($to instanceof ClassNode || $to instanceof GraphInterfaceNode || $to instanceof TraitNode || $to instanceof EnumNode);

            return new DeclarationMethodEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof PropertyNode) {
            assert($to instanceof ClassNode || $to instanceof TraitNode);

            return new DeclarationPropertyEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof ConstantNode) {
            assert($to instanceof ClassNode || $to instanceof GraphInterfaceNode);

            return new DeclarationConstantEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof EnumCaseNode) {
            assert($to instanceof EnumNode);

            return new DeclarationEnumCaseEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($to instanceof ClassNode) {
            if ($from instanceof ClassNode) {
                return new DeclarationExtendsEdge(from: $to, to: $from, meta: $this->meta);
            }
            if ($from instanceof GraphInterfaceNode) {
                return new DeclarationImplementsEdge(from: $to, to: $from, meta: $this->meta);
            }
            if ($from instanceof TraitNode) {
                return new DeclarationTraitUseEdge(from: $to, to: $from, meta: $this->meta);
            }
        }

        if ($to instanceof PropertyNode) {
            assert($from instanceof ClassNode || $from instanceof GraphInterfaceNode || $from instanceof EnumNode || $from instanceof TraitNode || $from instanceof BuiltinNode);

            return new DeclarationTypePropertyEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($to instanceof MethodNode || $to instanceof FunctionNode) {
            assert($from instanceof ClassNode || $from instanceof GraphInterfaceNode || $from instanceof EnumNode || $from instanceof TraitNode || $from instanceof BuiltinNode);

            return new DeclarationTypeReturnEdge(from: $to, to: $from, meta: $this->meta);
        }

        throw new \LogicException('Cannot invert DeclaredInEdge: unknown node combination.');
    }
}
