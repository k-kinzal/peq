<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeKind;

/**
 * A node that carries only an identifier.
 *
 * Tests of the graph itself care about how nodes are keyed and related, not about
 * which kind of PHP symbol they stand for. This double lets a test name a node
 * without also deciding whether it is a class, a method or a constant.
 */
final class StubNode implements Node
{
    /**
     * @param NodeId<Node>           $id          The identifier the node is keyed by
     * @param NodeKind               $kind        The kind the node reports
     * @param bool                   $resolved    Whether the node reports itself as resolved
     * @param null|FileMeta          $meta        Where the node reports itself as declared
     * @param null|SymbolDeclaration $declaration What the node reports its declaration to say
     */
    public function __construct(
        public readonly NodeId $id,
        public readonly NodeKind $kind = NodeKind::Unknown,
        public readonly bool $resolved = true,
        public readonly ?FileMeta $meta = null,
        public readonly ?SymbolDeclaration $declaration = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): NodeId
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function kind(): NodeKind
    {
        return $this->kind;
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

    /**
     * {@inheritdoc}
     */
    public function declaration(): ?SymbolDeclaration
    {
        return $this->declaration;
    }
}
