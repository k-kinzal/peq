<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

/**
 * Configuration options for the TreeReporter.
 */
final class TreeReporterOptions
{
    /**
     * @param null|int $level Maximum depth of the tree to display (null for unlimited)
     */
    public function __construct(
        public readonly ?int $level = null,
    ) {
        assert($level === null || $level >= 0);
    }
}
