<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

/**
 * Configuration options for the TreeReporter.
 */
final readonly class TreeReporterOptions
{
    /**
     * @param null|int $level Maximum depth of the tree to display (null for unlimited)
     */
    public function __construct(
        public ?int $level = null,
    ) {
        assert($level === null || $level >= 0);
    }
}
