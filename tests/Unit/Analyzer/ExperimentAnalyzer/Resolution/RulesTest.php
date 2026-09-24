<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Resolution;

use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class RulesTest extends TestCase
{
    public function testClassifyUnknownNodesNeverFallThroughToEagerEvaluation(): void
    {
        $rules = new \App\Analyzer\ExperimentAnalyzer\Resolution\Rules();
        self::assertSame(['UNSUPPORTED_EXPRESSION', 'No checked evaluation rule for Expr_Isset; conditional evaluation and effects are unknown.'], $rules->classify(new \PhpParser\Node\Expr\Isset_([])));
        self::assertSame(['structured-statement', ''], $rules->classify(new \PhpParser\Node\Stmt\If_(new \PhpParser\Node\Expr\Variable('a'))));
    }

    public function testPureRejectsSideEffectsInUnspecifiedOperandOrder(): void
    {
        $rules = new \App\Analyzer\ExperimentAnalyzer\Resolution\Rules();
        self::assertFalse($rules->pure(new \PhpParser\Node\Expr\BinaryOp\Plus(new \PhpParser\Node\Expr\PostInc(new \PhpParser\Node\Expr\Variable('i')), new \PhpParser\Node\Expr\Variable('i'))));
        self::assertTrue($rules->pure(new \PhpParser\Node\Expr\BooleanNot(new \PhpParser\Node\Expr\Variable('a'))));
    }

    public function testLocalWriteRequiresAnEvaluationRuleForCompoundSideEffects(): void
    {
        $node = new \PhpParser\Node\Expr\AssignOp\Plus(new \PhpParser\Node\Expr\Variable('a'), new \PhpParser\Node\Expr\PostInc(new \PhpParser\Node\Expr\Variable('a')));
        self::assertSame(['EVALUATION_ORDER', 'A compound assignment with effects in its right operand requires an evaluation-order rule.'], (new \App\Analyzer\ExperimentAnalyzer\Resolution\Rules())->localWrite($node));
    }
}
