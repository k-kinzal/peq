<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\Calls;

use App\Analyzer\Graph\Call\CallArgument;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Keeps original call syntax before name resolution replaces written aliases.
 */
final class WrittenCalls
{
    /**
     * @param list<Node> $nodes
     */
    public static function capture(array $nodes, string $contents): void
    {
        foreach ((new NodeFinder())->find($nodes, static fn (Node $node): bool => $node instanceof Expr\CallLike || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) as $node) {
            $offset = $node->getStartFilePos();
            $newline = strrpos($contents, "\n", $offset - strlen($contents));
            $node->setAttribute('peqStartColumn', $offset - ($newline === false ? -1 : $newline));
            if ($node instanceof Expr\CallLike) {
                $node->setAttribute('peqExpression', self::text($node, $contents));
                $arguments = [];
                foreach ($node->getRawArgs() as $argument) {
                    if ($argument instanceof Arg) {
                        $arguments[] = new CallArgument(self::text($argument, $contents), $argument->name?->toString(), $argument->unpack, self::type($argument->value));
                    }
                }
                $node->setAttribute('peqCallArguments', $arguments);
            }
        }
    }

    /**
     * Extracts bytes including original quoting, parentheses and expression spacing.
     */
    public static function text(Node $node, string $contents): string
    {
        return substr($contents, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1);
    }

    /**
     * Reads a literal's type without predicting a variable or evaluating an expression.
     */
    public static function type(Expr $expression): ?string
    {
        return match (true) {
            $expression instanceof Node\Scalar\String_, $expression instanceof Node\Scalar\InterpolatedString => 'string',
            $expression instanceof Node\Scalar\Int_ => 'int',
            $expression instanceof Node\Scalar\Float_ => 'float',
            $expression instanceof Expr\Array_ => 'array',
            $expression instanceof Expr\ConstFetch => match (strtolower($expression->name->toString())) {
                'true', 'false' => 'bool',
                'null' => 'null',
                default => null,
            },
            default => null,
        };
    }
}
