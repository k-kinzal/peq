<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\Graph\Graph;

/**
 * Data transfer object for the output of the InspectAction.
 */
final readonly class InspectActionOutput
{
    /**
     * @param Graph $graph the graph resulting from the inspection
     */
    public function __construct(
        public Graph $graph,
    ) {}
}
