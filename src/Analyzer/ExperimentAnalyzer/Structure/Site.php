<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Structure;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;

/**
 * A syntax fact, independent of whether the solver visits or understands it.
 */
final readonly class Site
{
    /**
     * Creates a source node and its named containment relationship.
     */
    public function __construct(
        public Occurrence $source,
        public string $syntax,
        public ?string $parent,
        public string $role,
        public ?string $target,
        public int $start,
        public int $end,
    ) {}
}
