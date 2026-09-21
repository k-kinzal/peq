<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Rejects constructs whose local flow would otherwise be silently misrepresented.
 */
final class SupportedSyntax
{
    /**
     * @param array<Node> $nodes
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function check(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->node($node);
        }
    }

    /**
     * Checks one syntax node without executing a nested callable body.
     *
     * @throws InspectionException If the requested analysis or encoding is rejected
     */
    public function node(Node $node): void
    {
        $reason = $this->unsupported($node);
        if ($reason !== null) {
            throw new InspectionException(sprintf('Cannot inspect line %d: %s.', $node->getStartLine(), $reason));
        }
        if ($node instanceof Expr\Closure) {
            foreach ($node->uses as $use) {
                if ($use->byRef) {
                    throw new InspectionException(sprintf('Cannot inspect line %d: closure captures by reference are not supported.', $use->getStartLine()));
                }
            }

            return;
        }
        if ($node instanceof Expr\ArrowFunction) {
            return;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $children = (get_object_vars($node)[$name] ?? null);
            foreach (is_array($children) ? $children : [$children] as $child) {
                if ($child instanceof Node) {
                    $this->node($child);
                }
            }
        }
    }

    /**
     * Names a construct whose local flow cannot be represented reliably.
     */
    public function unsupported(Node $node): ?string
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name
            && in_array(strtolower($node->name->toString()), ['extract', 'parse_str', 'mb_parse_str'], true)
            && (strtolower($node->name->toString()) === 'extract' || count($node->getArgs()) < 2)
        ) {
            return 'calls that dynamically introduce local variables are not supported';
        }

        return match (true) {
            $node instanceof Expr\Variable && !is_string($node->name) => 'variable variables are not supported',
            $node instanceof Expr\AssignRef, $node instanceof Stmt\Foreach_ && $node->byRef,
            $node instanceof Node\ArrayItem && $node->byRef, $node instanceof Node\Arg && $node->byRef => 'reference aliases are not supported',
            $node instanceof Stmt\TryCatch => 'exception flow (try/catch/finally) is not supported',
            $node instanceof Stmt\Goto_, $node instanceof Stmt\Label => 'goto flow is not supported',
            $node instanceof Stmt\Global_, $node instanceof Stmt\Static_ => 'shared variable storage is not supported',
            $node instanceof Expr\Eval_, $node instanceof Expr\Include_, $node instanceof Expr\Yield_, $node instanceof Expr\YieldFrom => 'dynamic execution and suspension are not supported',
            $node instanceof Expr\NullsafeMethodCall, $node instanceof Expr\NullsafePropertyFetch => 'nullsafe evaluation is not supported',
            default => null,
        };
    }
}
