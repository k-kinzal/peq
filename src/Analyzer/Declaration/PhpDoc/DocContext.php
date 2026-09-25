<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Keeps PHPDoc scopes beside source syntax without changing written signatures.
 */
final class DocContext extends NodeVisitorAbstract
{
    /**
     * @var list<?DocScope>
     */
    private array $stack = [];
    private ?DocScope $scope = null;

    /**
     * Indexes one file using the same namespace traversal as its PHP names.
     */
    public function __construct(private readonly DocIndex $index, private readonly NameResolver $names, private readonly DocParser $parser = new DocParser()) {}

    /**
     * Attaches a comment and indexes declarations in their lexical scope.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        $this->stack[] = $this->scope;
        $comment = $node->getDocComment();
        if ($comment === null && !$node instanceof Stmt\ClassLike) {
            return null;
        }
        $scope = new DocScope(clone $this->names->getNameContext(), $this->scope?->class, $this->scope?->parent, $this->scope->localTypes ?? []);
        if ($node instanceof Stmt\ClassLike) {
            $scope = new DocScope($scope->names, $node->namespacedName?->toString(), $node instanceof Stmt\Class_ ? $node->extends?->toString() : null);
        }
        $this->scope = $scope;
        if ($comment === null) {
            return null;
        }
        $doc = $this->parser->parse($comment->getText());
        $this->scope = $scope->withTypes($doc);
        $block = new DocBlock($doc, $this->scope);
        $node->setAttribute('peqDocBlock', $block);
        $names = match (true) {
            $node instanceof Stmt\ClassLike => [$scope->class ?? ''],
            $node instanceof Stmt\Function_ => [$node->namespacedName?->toString() ?? ''],
            $node instanceof Stmt\ClassMethod => [$scope->class.'::'.$node->name->toString()],
            $node instanceof Node\Param && $node->flags !== 0 && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) => [$scope->class.'::$'.$node->var->name],
            $node instanceof Stmt\Property => array_map(static fn (Node\PropertyItem $p): string => $scope->class.'::$'.$p->name->toString(), $node->props),
            default => [],
        };
        foreach ($names as $name) {
            $this->index->blocks[DocIndex::key($name)] = $block;
        }

        return null;
    }

    /**
     * Restores the surrounding class and template declarations.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        $this->scope = array_pop($this->stack);

        return null;
    }
}
