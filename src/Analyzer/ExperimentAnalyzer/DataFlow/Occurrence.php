<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

/**
 * One source occurrence, never a variable name shared across assignments.
 */
final readonly class Occurrence
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $variable,
        public int $line,
        public int $column,
        public int $endLine,
        public string $text,
    ) {}
}
