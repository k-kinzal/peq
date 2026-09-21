<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeKind;
use Override;

/**
 * Represents a function node in the dependency graph.
 *
 * Encapsulates information about a PHP global function including its identifier,
 * file location metadata, and whether it has been fully resolved during analysis.
 */
final readonly class FunctionNode implements Node
{
    /**
     * @param FunctionNodeId         $id          Unique identifier for this function
     * @param bool                   $resolved    Whether this node has been fully resolved during analysis
     * @param null|FileMeta          $meta        File location metadata (null if not available)
     * @param null|SymbolDeclaration $declaration What the source declares about it, or null when analysis did not read its declaration
     */
    public function __construct(
        public FunctionNodeId $id,
        public bool $resolved = false,
        public ?FileMeta $meta = null,
        public ?SymbolDeclaration $declaration = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function id(): FunctionNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): NodeKind
    {
        return NodeKind::Function;
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
