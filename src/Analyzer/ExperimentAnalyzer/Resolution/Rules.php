<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Admission rules are an allowlist. An unrecognized AST never inherits eager flow.
 */
final class Rules
{
    /**
     * Returns the rule whose preconditions are met, or an explicit boundary reason.
     *
     * @return array{string, string}
     */
    public function classify(Node $node): array
    {
        if ($node instanceof Stmt) {
            return match ($node::class) {
                Stmt\If_::class, Stmt\Expression::class, Stmt\Return_::class,
                Stmt\Echo_::class, Stmt\Nop::class => ['structured-statement', ''],
                default => ['UNSUPPORTED_STATEMENT', 'No checked transfer rule for '.$node->getType().'; its body and continuation remain unknown.'],
            };
        }
        if ($node instanceof Expr\Variable) {
            return is_string($node->name) ? ['local-read', ''] : ['DYNAMIC_VARIABLE', 'The variable name depends on a runtime value.'];
        }
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\PreInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostInc || $node instanceof Expr\PostDec) {
            return $this->localWrite($node);
        }
        if (in_array($node::class, [Expr\Ternary::class, Expr\BinaryOp\BooleanAnd::class, Expr\BinaryOp\BooleanOr::class, Expr\BinaryOp\LogicalAnd::class, Expr\BinaryOp\LogicalOr::class, Expr\BinaryOp\Coalesce::class], true)) {
            return ['conditional-expression', ''];
        }
        if ($node instanceof Expr\CallLike) {
            return $node->isFirstClassCallable() ? ['CALLABLE_CREATION', 'A callable is created, not invoked; target binding is not solved.'] : ['OPAQUE_CALL', 'Return values, implicit local reads, reference effects and exceptional continuation are not solved.'];
        }
        if ($this->pure($node)) {
            return ['pure-expression', ''];
        }

        return ['UNSUPPORTED_EXPRESSION', 'No checked evaluation rule for '.$node->getType().'; conditional evaluation and effects are unknown.'];
    }

    /**
     * Checks address and evaluation-order preconditions for a local write.
     *
     * @return array{string, string}
     */
    public function localWrite(Expr\Assign|Expr\AssignOp|Expr\PostDec|Expr\PostInc|Expr\PreDec|Expr\PreInc $node): array
    {
        if ($node instanceof Expr\AssignOp && !$node instanceof Expr\AssignOp\Coalesce && !$this->pure($node->expr)) {
            return ['EVALUATION_ORDER', 'A compound assignment with effects in its right operand requires an evaluation-order rule.'];
        }

        return $node->var instanceof Expr\Variable && is_string($node->var->name)
            ? ['local-write', ''] : ['INDIRECT_WRITE', 'The write may involve aliases, offsets, destructuring or heap storage.'];
    }

    /**
     * Admits only side-effect-free, explicitly named expression shapes.
     */
    public function pure(Node $node): bool
    {
        if ($node instanceof Expr\Variable) {
            return is_string($node->name);
        }
        if (in_array($node::class, [Node\Scalar\Int_::class, Node\Scalar\Float_::class, Node\Scalar\String_::class], true)) {
            return true;
        }
        if ($node instanceof Expr\ConstFetch) {
            return in_array(strtolower($node->name->toString()), ['true', 'false', 'null'], true);
        }
        if (in_array($node::class, [Expr\BooleanNot::class, Expr\UnaryMinus::class, Expr\UnaryPlus::class, Expr\BitwiseNot::class], true)) {
            return $this->pure($node->expr);
        }
        if ($node instanceof Expr\BinaryOp && in_array($node->getType(), [
            'Expr_BinaryOp_Plus', 'Expr_BinaryOp_Minus', 'Expr_BinaryOp_Mul', 'Expr_BinaryOp_Div',
            'Expr_BinaryOp_Mod', 'Expr_BinaryOp_Pow', 'Expr_BinaryOp_Concat', 'Expr_BinaryOp_Identical',
            'Expr_BinaryOp_NotIdentical', 'Expr_BinaryOp_Equal', 'Expr_BinaryOp_NotEqual',
            'Expr_BinaryOp_Smaller', 'Expr_BinaryOp_SmallerOrEqual', 'Expr_BinaryOp_Greater',
            'Expr_BinaryOp_GreaterOrEqual', 'Expr_BinaryOp_Spaceship', 'Expr_BinaryOp_BitwiseAnd',
            'Expr_BinaryOp_BitwiseOr', 'Expr_BinaryOp_BitwiseXor', 'Expr_BinaryOp_ShiftLeft',
            'Expr_BinaryOp_ShiftRight', 'Expr_BinaryOp_LogicalXor',
        ], true)) {
            return $this->pure($node->left) && $this->pure($node->right);
        }

        return false;
    }
}
