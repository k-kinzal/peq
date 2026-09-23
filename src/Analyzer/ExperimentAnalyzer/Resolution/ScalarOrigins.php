<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Scalar proofs rule out hidden object conversions and destructors in checked rules.
 */
final class ScalarOrigins
{
    /**
     * Checks a declared runtime parameter type, never trusting a PHPDoc annotation.
     */
    public static function parameter(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return self::parameter($type->type);
        }
        if ($type instanceof Node\UnionType) {
            return $type->types !== [] && count(array_filter($type->types, self::parameter(...))) === count($type->types);
        }

        return $type instanceof Node\Identifier && in_array(strtolower($type->name), ['int', 'float', 'string', 'bool', 'true', 'false', 'null'], true);
    }

    /**
     * Proves every reaching definition scalar before admitting a coercive operand.
     */
    public static function expression(Expr $node, State $state, DependencyGraph $graph): bool
    {
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            $definitions = array_keys($state->definitions['$'.$node->name] ?? []);

            return self::inputs($definitions, $graph);
        }
        if ($node instanceof Expr\BooleanNot) {
            return (new Rules())->pure($node->expr);
        }
        if ($node instanceof Expr\BinaryOp) {
            return self::expression($node->left, $state, $graph) && self::expression($node->right, $state, $graph);
        }
        if ($node instanceof Expr\UnaryMinus || $node instanceof Expr\UnaryPlus || $node instanceof Expr\BitwiseNot) {
            return self::expression($node->expr, $state, $graph);
        }

        return self::literal($node);
    }

    /**
     * Distinguishes literal scalar/null values from arrays, objects and constants.
     */
    public static function literal(Node $node): bool
    {
        return $node instanceof Node\Scalar\Int_ || $node instanceof Node\Scalar\Float_ || $node instanceof Node\Scalar\String_
            || ($node instanceof Expr\ConstFetch && in_array(strtolower($node->name->toString()), ['true', 'false', 'null'], true));
    }

    /**
     * @param list<string> $inputs
     */
    public static function inputs(array $inputs, DependencyGraph $graph): bool
    {
        return $inputs !== [] && count(array_filter($inputs, static fn (string $id): bool => $graph->scalars[$id] ?? false)) === count($inputs);
    }

    /**
     * Rejects potential implicit calls or heap comparisons without scalar operands.
     */
    public static function effects(Expr $node, State $state, DependencyGraph $graph): bool
    {
        if ($node instanceof Expr\BinaryOp && !in_array($node::class, [Expr\BinaryOp\BooleanAnd::class, Expr\BinaryOp\BooleanOr::class, Expr\BinaryOp\LogicalAnd::class, Expr\BinaryOp\LogicalOr::class, Expr\BinaryOp\Coalesce::class], true)) {
            return !self::expression($node, $state, $graph);
        }
        if ($node instanceof Expr\UnaryMinus || $node instanceof Expr\UnaryPlus || $node instanceof Expr\BitwiseNot) {
            return !self::expression($node->expr, $state, $graph);
        }
        if ($node instanceof Expr\AssignOp && !$node instanceof Expr\AssignOp\Coalesce) {
            return !self::expression($node->var, $state, $graph) || !self::expression($node->expr, $state, $graph);
        }

        return false;
    }
}
