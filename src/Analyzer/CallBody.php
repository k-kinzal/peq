<?php

declare(strict_types=1);

namespace App\Analyzer;

use Closure;
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
     * @param null|Closure(Node): bool $select Retain only expressions the consumer records
     */
    public function __construct(private readonly ?Closure $select = null) {}

    /**
     * Records expressions in this callable's scope.
     */
    #[Override]
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        if ($this->select === null || ($this->select)($node)) {
            $this->expressions[] = $node;
        }

        return null;
    }
}
