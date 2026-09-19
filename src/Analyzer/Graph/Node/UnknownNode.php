<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodeKind;
use Override;

/**
 * Represents an unknown node in the dependency graph.
 *
 * Encapsulates information about nodes that could not be resolved or classified
 * during analysis. These are typically used as placeholders for unresolved
 * dependencies or external references not present in the analyzed codebase.
 */
final readonly class UnknownNode implements Node
{
    /**
     * @param UnknownNodeId $id       Unique identifier for this unknown node
     * @param bool          $resolved Whether this node has been fully resolved during analysis (typically false)
     * @param null|FileMeta $meta     File location metadata (typically null for unknown nodes)
     */
    public function __construct(
        public UnknownNodeId $id,
        public bool $resolved = false,
        public ?FileMeta $meta = null,
    ) {}

    /**
     * Returns a placeholder standing in for a symbol the graph has not seen yet.
     *
     * An edge may name a symbol before analysis reaches its declaration, or name
     * one that lives outside the analyzed sources. The graph records this
     * placeholder for it so that the edge is never left dangling, and replaces the
     * placeholder as soon as the real node arrives.
     *
     * @param NodeId<Node> $id The identifier the edge refers to
     *
     * @example A referenced but unseen symbol stands in for itself
     *     $named = \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice');
     *     \App\Analyzer\Graph\Node\UnknownNode::standingInFor($named)->kind() // => \App\Analyzer\Graph\NodeKind::Unknown
     *
     * @return self An unresolved placeholder for that identifier
     */
    public static function standingInFor(NodeId $id): self
    {
        return new self(
            id: $id instanceof UnknownNodeId ? $id : new UnknownNodeId($id->toString()),
        );
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function id(): UnknownNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): NodeKind
    {
        return NodeKind::Unknown;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function resolved(): bool
    {
        return $this->resolved;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function meta(): ?FileMeta
    {
        return $this->meta;
    }
}
