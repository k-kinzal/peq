<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Analyzer\Graph\Resolution\TypeConstraint;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

/**
 * Records receiver calls against the same declarations for either analysis engine.
 */
final readonly class BodyCallRecorder
{
    /**
     * Prepares call recording against the indexed declarations.
     */
    public function __construct(private Graph $graph, private ClassHierarchy $hierarchy) {}

    /**
     * @param list<Node> $body
     */
    public function record(array $body, FunctionNode|MethodNode $source): void
    {
        $types = new ReceiverBinding($this->hierarchy, $source);
        $nodes = $this->expressions($body);
        foreach ($nodes as $node) {
            if ($node instanceof Expr\Assign) {
                $types->assign($node);
            }
        }
        foreach ($nodes as $node) {
            if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
                $this->call($node, $source, $types);
            }
        }
    }

    /** @param list<Node> $nodes
     * @return list<Node>
     */
    public function expressions(array $nodes): array
    {
        $visitor = new CallBody();
        (new \PhpParser\NodeTraverser($visitor))->traverse($nodes);

        return $visitor->expressions;
    }

    /**
     * Records one call, keeping an unresolved occurrence when its target is unknown.
     */
    public function call(Expr\MethodCall|Expr\NullsafeMethodCall $call, FunctionNode|MethodNode $source, ReceiverBinding $types): void
    {
        if ($call->var instanceof Expr\Variable && $call->var->name === 'this' && $call->name instanceof Node\Identifier) {
            return;
        }
        $file = $source->meta()?->path;
        if ($file === null) {
            return;
        }
        $meta = new FileMeta($file, $call->getStartLine(), 1, $call->getStartFilePos());
        $receiver = $types->of($call->var);
        $owners = TypeConstraint::of($receiver)->names();
        if ($owners === [] || !$call->name instanceof Node\Identifier) {
            $column = $call->getAttribute('peqStartColumn', 1);
            assert(is_int($column));
            $meta = new FileMeta($file, $call->getStartLine(), $column, $call->getStartFilePos());
            $target = new UnknownNode(new UnknownNodeId('unresolved-call@'.$file.':'.$meta->line.':'.$meta->column), false, $meta);
            $this->graph->addNode($target);
            $this->graph->addEdge(new MethodCallEdge($source, $target, $meta, expression: (new Standard())->prettyPrintExpr($call)));

            return;
        }
        foreach ($this->targets($receiver, $call->name->toString()) as $target) {
            $this->graph->addNode($target);
            $this->graph->addEdge(new MethodCallEdge($source, $target, $meta, $receiver));
        }
    }

    /**
     * Resolves each union alternative without inventing a method on every intersection member.
     *
     * @return list<MethodNode>
     */
    public function targets(string $receiver, string $method): array
    {
        $targets = [];
        foreach (TypeConstraint::of($receiver)->alternatives as $requirements) {
            $declared = array_values(array_filter(array_map(fn (string $owner): ?MethodNode => $this->hierarchy->method($owner, $method), $requirements)));
            $candidates = $declared === [] ? array_map(static fn (string $owner): MethodNode => new MethodNode(MethodNodeId::of($owner, $method)), $requirements) : $declared;
            foreach ($candidates as $target) {
                $targets[$target->id()->toString()] = $target;
            }
        }

        return array_values($targets);
    }
}
