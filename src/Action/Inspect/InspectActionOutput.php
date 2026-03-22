<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\Graph\Graph;

/**
 * Data transfer object for the output of the InspectAction.
 */
final class InspectActionOutput
{
    /**
     * @param Graph $graph the graph resulting from the inspection
     */
    public function __construct(
        public readonly Graph $graph,
    ) {}
}
