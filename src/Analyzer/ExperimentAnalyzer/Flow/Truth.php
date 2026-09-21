<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Only syntactically constant conditions are pruned; no value inference is claimed.
 */
final class Truth
{
    /**
     * Reads a literal condition without inferring values of variables.
     */
    public static function of(Expr $node): ?bool
    {
        if ($node instanceof Expr\ConstFetch) {
            return match (strtolower($node->name->toString())) {
                'true' => true,
                'false', 'null' => false,
                default => null,
            };
        }
        if ($node instanceof Scalar\Int_ || $node instanceof Scalar\Float_ || $node instanceof Scalar\String_) {
            return (bool) $node->value;
        }
        if ($node instanceof Expr\BooleanNot) {
            $inner = self::of($node->expr);

            return $inner === null ? null : !$inner;
        }

        return null;
    }
}
