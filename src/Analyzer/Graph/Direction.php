<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * The direction in which a dependency relation is read.
 *
 * Every edge in the graph exists in both directions: the relation as it is
 * written in the source code, and the relation generated for it in the opposite
 * direction. This enum names those two readings, and is the single closed type
 * behind the `--direction` option, the traversal strategy and the classification
 * of an edge kind.
 */
enum Direction: string
{
    /** Reads the graph away from the subject: what the subject depends on */
    case Uses = 'uses';

    /** Reads the graph towards the subject: what depends on the subject */
    case UsedBy = 'used-by';
}
