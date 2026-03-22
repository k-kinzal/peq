<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Represents metadata about the location of a code element in a source file.
 *
 * FileMeta stores information about where a node or edge is defined in the source code,
 * including the file path, line number, and column position. This metadata is useful
 * for error reporting, code navigation, and debugging purposes.
 */
final class FileMeta
{
    /**
     * The filename extracted from the path.
     */
    public readonly string $name;

    /**
     * @param string $path   Full path to the source file
     * @param int    $line   Line number in the file (1-indexed)
     * @param int    $column Column position in the line (1-indexed)
     */
    public function __construct(
        public readonly string $path,
        public readonly int $line,
        public readonly int $column,
    ) {
        assert($this->line > 0);
        assert($this->column > 0);

        $this->name = basename($this->path);
    }
}
