<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * Identifies a branch supported only by possible dispatches, in either direction.
 */
final class RelationNotice
{
    /**
     * Reports whether every relation to this child is an inferred call target.
     */
    public static function possible(Graph $graph, ?Node $parent, Node $child, Direction $direction): bool
    {
        if ($parent === null) {
            return false;
        }
        $possible = false;
        foreach ($graph->edges($parent->id()) as $edge) {
            if ($edge->kind()->direction() !== $direction || $edge->to()->toString() !== $child->id()->toString()) {
                continue;
            }
            $kind = $direction === Direction::UsedBy ? $edge->invert()->kind() : $edge->kind();
            if ($kind !== EdgeKind::PossibleCall) {
                return false;
            }
            $possible = true;
        }

        return $possible;
    }
}
