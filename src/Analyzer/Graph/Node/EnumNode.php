<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeKind;
use Override;

/**
 * Represents an enum node in the dependency graph.
 *
 * Encapsulates information about a PHP enum including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final readonly class EnumNode implements Node
{
    /**
     * @param EnumNodeId             $id          Unique identifier for this enum
     * @param bool                   $resolved    Whether this node has been fully resolved during analysis
     * @param null|FileMeta          $meta        File location metadata (null if not available)
     * @param null|SymbolDeclaration $declaration What the source declares about it, or null when analysis did not read its declaration
     */
    public function __construct(
        public EnumNodeId $id,
        public bool $resolved = false,
        public ?FileMeta $meta = null,
        public ?SymbolDeclaration $declaration = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function id(): NodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): NodeKind
    {
        return NodeKind::Enum;
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

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function declaration(): ?SymbolDeclaration
    {
        return $this->declaration;
    }
}
