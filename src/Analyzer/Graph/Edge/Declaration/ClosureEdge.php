<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Declaration;

use App\Analyzer\Graph\AuthoredEdge;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\DeclaredInEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClosureNode;
use Override;

/**
 * Lexical containment of a closure; declaring it does not execute its body.
 */
final readonly class ClosureEdge extends AuthoredEdge
{
    /**
     * Records the immediately enclosing callable, including another closure.
     */
    public function __construct(Node $owner, ClosureNode $closure)
    {
        parent::__construct($owner, $closure, $closure->meta());
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::DeclarationClosure;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function invert(): Edge
    {
        return new DeclaredInEdge($this);
    }
}
