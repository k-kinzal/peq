<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence;

/**
 * Machine-readable evidence for an unresolved rule, suitable for a bug report.
 */
final readonly class Issue
{
    /**
     * @param list<string> $affects
     */
    public function __construct(
        public string $code,
        public string $nodeId,
        public string $rule,
        public string $reason,
        public Occurrence $source,
        public array $affects = ['value', 'control', 'effects'],
    ) {}
}
