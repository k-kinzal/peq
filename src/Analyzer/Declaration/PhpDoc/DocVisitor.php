<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Graph\Edge\Declaration\PhpDocEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node as Symbol;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeKind;
use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * Attributes a comment to its declaration, or to the body that contains it.
 */
final class DocVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<array{?DocScope, list<Symbol>}>
     */
    private array $stack = [];
    private ?DocScope $scope = null;

    /**
     * @var list<Symbol>
     */
    private array $sources = [];

    /**
     * @var array<string, true>
     */
    private array $classes = [];

    /**
     * Records comments against declarations already collected by the engine.
     */
    public function __construct(
        private readonly Graph $graph,
        private readonly string $file,
        private readonly DocParser $parser,
        private readonly NameResolver $names,
    ) {
        foreach ($graph->nodes() as $symbol) {
            if (in_array($symbol->kind(), [NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum], true) && $symbol->resolved()) {
                $this->classes[strtolower($symbol->id()->toString())] = true;
            }
        }
    }

    /**
     * Enters a lexical scope and records the comment attached to its syntax node.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        $this->stack[] = [$this->scope, $this->sources];
        $scope = new DocScope(clone $this->names->getNameContext(), $this->scope?->class, $this->scope?->parent, $this->scope->localTypes ?? [], $this->classes);
        if ($node instanceof Stmt\ClassLike) {
            $scope = new DocScope($scope->names, $node->namespacedName?->toString(), $node instanceof Stmt\Class_ ? $node->extends?->toString() : null, classes: $this->classes);
        }
        $this->sources = DocOwners::of($node, $scope, $this->graph) ?? $this->sources;
        $comment = $node->getDocComment();
        if ($comment !== null) {
            $doc = $this->parser->parse($comment->getText());
            $scope = $scope->withTypes($doc);
            $this->record($doc, $scope, $comment->getStartLine(), $comment->getStartFilePos());
        }
        $this->scope = $scope;

        return null;
    }

    /**
     * Restores the outer declaration and its type variables.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        [$this->scope, $this->sources] = array_pop($this->stack) ?? [null, []];

        return null;
    }

    /**
     * Each valid tag contributes its own source occurrence.
     */
    public function record(PhpDocNode $doc, DocScope $scope, int $startLine, int $startOffset): void
    {
        foreach ($doc->getTags() as $tag) {
            $line = $tag->getAttribute('startLine');
            $offset = $tag->getAttribute('peqOffset');
            $meta = new FileMeta($this->file, $startLine + (is_int($line) ? $line - 1 : 0), 1, is_int($offset) && $startOffset >= 0 ? $startOffset + $offset : null);
            foreach (DocNames::of($tag->value, $scope) as $name) {
                $target = new ClassNode(ClassNodeId::of($name));
                foreach ($this->sources as $source) {
                    $this->graph->addNode($target);
                    $this->graph->addEdge(new PhpDocEdge($source, $target, $meta));
                }
            }
        }
    }
}
