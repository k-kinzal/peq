<?php

declare(strict_types=1);

namespace App\Reporter\TreeReporter;

/**
 * How much of the dependency tree a TreeReporter prints.
 *
 * The level bound is stated the same way here as in the application
 * configuration it comes from: a positive number of levels below the root symbol,
 * or null for the whole tree.
 */
final class TreeReporterOptions
{
    /**
     * @param null|int $level Deepest level below the root to print, or null for the whole tree
     */
    public function __construct(
        public readonly ?int $level = null,
    ) {
        assert($level === null || $level > 0, 'A printed level bound must be a positive number of levels');
    }
}
