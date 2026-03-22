<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * Represents a builtin type node in the dependency graph.
 *
 * Encapsulates information about PHP builtin types (e.g., string, int, array)
 * including their identifier and resolution status. Builtin types typically
 * do not have file metadata as they are part of the PHP language itself.
 */
final class BuiltinNode implements Node
{
    /**
     * @param BuiltinNodeId $id       Unique identifier for this builtin type
     * @param bool          $resolved Whether this node has been fully resolved during analysis
     * @param null|FileMeta $meta     File location metadata (typically null for builtin types)
     */
    public function __construct(
        public readonly BuiltinNodeId $id,
        public readonly bool $resolved = false,
        public readonly ?FileMeta $meta = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): BuiltinNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return NodeKind::Builtin;
    }

    /**
     * {@inheritdoc}
     */
    public function resolved(): bool
    {
        return $this->resolved;
    }

    /**
     * {@inheritdoc}
     */
    public function meta(): ?FileMeta
    {
        return $this->meta;
    }
}
