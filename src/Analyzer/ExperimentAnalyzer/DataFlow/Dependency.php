<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

/**
 * An arrow from an occurrence to something its value or execution depends on.
 */
final readonly class Dependency
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $kind,
        public ?string $branch = null,
    ) {}
}
