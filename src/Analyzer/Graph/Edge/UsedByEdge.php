<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\EdgeTrait;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;

/**
 * Represents a reverse usage relationship (e.g., function called by method).
 */
final readonly class UsedByEdge implements Edge
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
        return EdgeKind::UsedBy;
    }

    public function invert(): Edge
    {
        $from = $this->fromNode;
        $to = $this->toNode;

        if ($from instanceof FunctionNode) {
            assert($to instanceof MethodNode || $to instanceof FunctionNode);

            return new FunctionCallEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof ConstantNode) {
            assert($to instanceof MethodNode || $to instanceof FunctionNode);

            return new ConstFetchEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof ClassNode) {
            assert($to instanceof MethodNode || $to instanceof FunctionNode);

            return new InstantiationEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof PropertyNode) {
            assert($to instanceof MethodNode || $to instanceof FunctionNode);

            return new PropertyAccessEdge(from: $to, to: $from, meta: $this->meta);
        }

        if ($from instanceof MethodNode) {
            assert($to instanceof MethodNode || $to instanceof FunctionNode);

            return new MethodCallEdge(from: $to, to: $from, meta: $this->meta);
        }

        throw new \LogicException('Cannot invert UsedByEdge: unknown node combination.');
    }
}
