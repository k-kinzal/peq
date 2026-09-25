<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\Calls;

use App\Analyzer\Declaration\DeclarationReader;
use App\Analyzer\Declaration\ExpressionText;
use App\Analyzer\Graph\Call\CallArgument;
use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\Call\CallSite;
use App\Analyzer\Graph\Edge\Declaration\ClosureEdge;
use App\Analyzer\Graph\Edge\Usage\CallableReferenceEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node as Symbol;
use App\Analyzer\Graph\Node\ClosureNode;
use App\Analyzer\Graph\NodeId\ClosureNodeId;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Separates lexical call scopes while retaining their enclosing dependency owner.
 */
final class CallSites extends NodeVisitorAbstract
{
    /**
     * @var array<string, CallSite>
     */
    public array $sites = [];

    /**
     * @var array<string, list<Node>>
     */
    public array $expressions = [];

    /**
     * @var list<ClosureNode>
     */
    public array $closures = [];

    /**
     * @var list<ClosureEdge>
     */
    public array $declarations = [];

    /**
     * @var list<Symbol>
     */
    private array $scopes;

    /**
     * Begins in a named function or method; nested named declarations are read separately.
     */
    public function __construct(private readonly Symbol $owner, private readonly string $file)
    {
        $this->scopes = [$owner];
    }

    /**
     * @param list<Node> $body
     */
    public static function of(array $body, Symbol $owner, string $file): self
    {
        $sites = new self($owner, $file);
        (new NodeTraverser($sites))->traverse($body);

        return $sites;
    }

    /**
     * Attaches the same source facts to declared and possible targets of each site.
     *
     * @param array<string, self> $scopes
     */
    public static function attach(Graph $graph, array $scopes): Graph
    {
        $result = new Graph();
        $result->addNodes($graph->nodes());
        foreach ($scopes as $scope) {
            $result->addNodes($scope->closures);
            $result->addEdges($scope->declarations);
        }
        foreach ($graph->forwardEdges() as $edge) {
            $meta = $edge->meta();
            $site = $scopes[$edge->from()->toString()]->sites[$meta->offset.':'.$meta->endOffset] ?? null;
            $call = in_array($edge->kind(), [EdgeKind::FunctionCall, EdgeKind::MethodCall, EdgeKind::StaticCall, EdgeKind::Instantiation, EdgeKind::PossibleCall], true);
            if ($call && $site !== null && !$edge instanceof CallOccurrence) {
                $edge = $site->callableReference ? new CallableReferenceEdge($edge, $site) : new CallOccurrence($edge, $site);
            }
            $result->addEdge($edge);
        }

        return $result;
    }

    /**
     * Records call syntax in the innermost closure or named callable.
     */
    #[Override]
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        $caller = $this->scopes[array_key_last($this->scopes)] ?? $this->owner;
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $meta = $this->position($node);
            $closure = new ClosureNode(new ClosureNodeId($this->owner->id()->toString(), $meta->line, $meta->column), $meta, $this->owner, DeclarationReader::forCallable($node, []));
            $this->closures[] = $closure;
            $this->declarations[] = new ClosureEdge($caller, $closure);
            $this->scopes[] = $closure;
        }
        $caller = $this->scopes[array_key_last($this->scopes)] ?? $this->owner;
        $this->expressions[$caller->id()->toString()][] = $node;
        if ($node instanceof Expr\CallLike && $node->getStartLine() > 0 && $node->getStartFilePos() >= 0) {
            $this->sites[$node->getStartFilePos().':'.$node->getEndFilePos()] = new CallSite(
                $caller,
                $this->owner,
                $this->position($node),
                $node->getEndFilePos(),
                $this->expression($node),
                $this->arguments($node),
                $node->isFirstClassCallable(),
            );
        }

        return null;
    }

    /**
     * Restores the outer scope after the nested callable's body.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            array_pop($this->scopes);
        }

        return null;
    }

    /**
     * Uses byte columns and offsets so calls on the same line remain distinct.
     */
    public function position(Node $node): FileMeta
    {
        $column = $node->getAttribute('peqStartColumn', 1);
        assert(is_int($column));

        return new FileMeta($this->file, $node->getStartLine(), $column, $node->getStartFilePos(), $node->getEndFilePos() < 0 ? null : $node->getEndFilePos());
    }

    /**
     * Falls back to readable syntax only for synthetic trees without original text.
     */
    public function expression(Expr\CallLike $node): string
    {
        $text = $node->getAttribute('peqExpression');

        return is_string($text) ? $text : (ExpressionText::of($node) ?? '');
    }

    /**
     * @return list<CallArgument>
     */
    public function arguments(Expr\CallLike $node): array
    {
        $written = $node->getAttribute('peqCallArguments');
        if (is_array($written)) {
            $arguments = [];
            foreach ($written as $argument) {
                if ($argument instanceof CallArgument) {
                    $arguments[] = $argument;
                }
            }

            return $arguments;
        }
        $arguments = [];
        foreach ($node->getRawArgs() as $argument) {
            if ($argument instanceof Node\Arg) {
                $arguments[] = new CallArgument(ExpressionText::argument($argument), $argument->name?->toString(), $argument->unpack, WrittenCalls::type($argument->value));
            }
        }

        return $arguments;
    }
}
