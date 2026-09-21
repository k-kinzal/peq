<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Usage;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use Override;

/**
 * Represents a constant fetch relationship.
 */
final readonly class ConstFetchEdge extends AuthoredEdge
{
    /**
     * @param FunctionNode|MethodNode   $from The node the relation starts at
     * @param ConstantNode|EnumCaseNode $to   The node the relation points at
     * @param FileMeta                  $meta Where in the source code the relation is written
     */
    public function __construct(
        FunctionNode|MethodNode $from,
        ConstantNode|EnumCaseNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::ConstFetch
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::ConstFetch;
    }

    /**
     * Returns this relation read in the opposite direction.
     *
     * @return Edge A UsedByEdge carrying this edge, which inverts back into it
     */
    #[Override]
    public function invert(): Edge
    {
        return new UsedByEdge($this);
    }
}
