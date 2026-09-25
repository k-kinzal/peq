<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\Node\ClosureNode;
use App\Analyzer\Graph\NodeId;
use Override;

/**
 * A lexical callable identified by its containing symbol and source position.
 *
 * @implements NodeId<ClosureNode>
 */
final readonly class ClosureNodeId implements NodeId
{
    /**
     * The owner distinguishes separate imports of the same trait body.
     */
    public function __construct(public string $owner, public int $line, public int $column) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function toString(): string
    {
        return $this->owner.'{closure@'.$this->line.':'.$this->column.'}';
    }
}
