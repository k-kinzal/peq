<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use Override;

/**
 * Represents a constant declaration relationship within a class/interface.
 */
final readonly class ConstantEdge extends AuthoredEdge
{
    /**
     * @param ClassNode|EnumNode|GraphInterfaceNode $from The node the relation starts at
     * @param ConstantNode                          $to   The node the relation points at
     * @param FileMeta                              $meta Where in the source code the relation is written
     */
    public function __construct(
        ClassNode|EnumNode|GraphInterfaceNode $from,
        ConstantNode $to,
        FileMeta $meta,
    ) {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Returns the kind of relationship this edge represents.
     *
     * @return EdgeKind Always EdgeKind::DeclarationConstant
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationConstant;
    }

    /**
     * Returns this relation read in the opposite direction.
     *
     * @return Edge A DeclaredInEdge carrying this edge, which inverts back into it
     */
    #[Override]
    public function invert(): Edge
    {
        return new DeclaredInEdge($this);
    }
}
