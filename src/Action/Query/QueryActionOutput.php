<?php

declare(strict_types=1);

namespace App\Action\Query;

use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultTable;

/**
 * What a query produced.
 *
 * The result table is the answer, and carries its own columns and its own status, so
 * nothing downstream has to be told separately whether the query found anything.
 *
 * The graph comes with it because a drawing of the answer needs it. A query binds the
 * symbols it was written about and usually not the relations between them, so a
 * reporter asked to draw what was found has to read those relations back out of the
 * graph the answer came from.
 */
final class QueryActionOutput
{
    /**
     * @param ResultTable  $result What the query answered
     * @param ElementGraph $graph  The graph it was answered from
     */
    public function __construct(
        public readonly ResultTable $result,
        public readonly ElementGraph $graph,
    ) {}
}
