<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use Override;

/**
 * A class-like dependency written in a PHPDoc annotation.
 */
final readonly class PhpDocEdge extends AuthoredEdge
{
    /**
     * Records the documented dependency at its annotation's source position.
     */
    public function __construct(Node $from, Node $to, FileMeta $meta)
    {
        parent::__construct($from, $to, $meta);
    }

    /**
     * Identifies this relation as documentation, without implying a runtime call.
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::PhpDoc;
    }

    /**
     * Reads the same documented dependency in the opposite direction.
     */
    #[Override]
    public function invert(): Edge
    {
        return new DeclaredInEdge($this);
    }
}
