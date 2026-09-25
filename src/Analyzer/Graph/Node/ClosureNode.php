<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Node;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClosureNodeId;
use App\Analyzer\Graph\NodeKind;
use Override;

/**
 * An anonymous function or arrow function whose body is a separate call scope.
 */
final readonly class ClosureNode implements Node
{
    /**
     * Retains the enclosing named symbol for dependency projection.
     */
    public function __construct(
        public ClosureNodeId $id,
        public FileMeta $meta,
        public Node $owner,
        public SymbolDeclaration $declaration,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function id(): ClosureNodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): NodeKind
    {
        return NodeKind::Closure;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function resolved(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function meta(): FileMeta
    {
        return $this->meta;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function declaration(): SymbolDeclaration
    {
        return $this->declaration;
    }
}
