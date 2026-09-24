<?php

declare(strict_types=1);

namespace App\Analyzer;

use Override;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Selects expressions without attributing a nested declaration's calls to its container.
 */
final class CallBody extends NodeVisitorAbstract
{
    /**
     * @var list<Node>
     */
    public array $expressions = [];

    /**
     * Records expressions in this callable's scope.
     */
    #[Override]
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        $this->expressions[] = $node;

        return null;
    }
}
