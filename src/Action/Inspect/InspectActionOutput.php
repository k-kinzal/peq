<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * What an inspection produced.
 *
 * The symbol is the node the requested name resolved to, not the name itself: the
 * inspection has already established that the graph holds it, so nothing downstream
 * has to repeat the lookup or handle its absence.
 */
final readonly class InspectActionOutput
{
    /**
     * @param Graph $graph  The dependency graph built for the configured path
     * @param Node  $symbol The node the requested symbol name resolved to
     */
    public function __construct(
        public Graph $graph,
        public Node $symbol,
    ) {}
}
