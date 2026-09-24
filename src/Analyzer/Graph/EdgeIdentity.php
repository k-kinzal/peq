<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PossibleCallEdge;

/**
 * Distinguishes occurrences and their evidence, including opposite readings.
 */
final class EdgeIdentity
{
    /**
     * Identifies an occurrence by its endpoints, kind, source position and evidence.
     */
    public static function of(Edge $edge): string
    {
        if ($edge instanceof InverseEdge) {
            return 'inverse:'.self::of($edge->invert());
        }
        $meta = $edge->meta();

        return $edge->from()->toString().'|'.$edge->kind()->value.'|'.$edge->to()->toString().'|'.hash('sha256', serialize([
            $meta->path, $meta->line, $meta->column, $meta->offset, self::evidence($edge),
        ]));
    }

    /**
     * Returns the source facts or dispatch evidence distinguishing an occurrence.
     *
     * @return array<string, null|list<string>|string>
     */
    public static function evidence(Edge $edge): array
    {
        return match (true) {
            $edge instanceof PossibleCallEdge => ['receiverType' => $edge->receiverType, 'declaredTarget' => $edge->call->to()->toString(), 'implementationType' => $edge->implementationType],
            $edge instanceof MethodCallEdge && $edge->expression !== null => ['expression' => $edge->expression],
            $edge instanceof MethodCallEdge => $edge->receiverType === null ? [] : ['receiverType' => $edge->receiverType],
            $edge instanceof AttributeEdge => ['arguments' => $edge->arguments, 'parameter' => $edge->parameter],
            default => [],
        };

    }
}
