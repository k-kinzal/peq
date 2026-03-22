<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Graph\Graph;

/**
 * Analyzer interface for analyzing PHP codebases and generating dependency graphs.
 *
 * Implementations of this interface are responsible for parsing PHP source code
 * and extracting relationships between code elements (classes, methods, functions, etc.)
 * to build a comprehensive graph representation.
 */
interface Analyzer
{
    /**
     * Analyzes the PHP codebase at the specified path and generates a dependency graph.
     *
     * @param string $path The file system path to analyze (file or directory)
     *
     * @return Graph The generated dependency graph containing nodes and edges
     */
    public function analyze(string $path): Graph;
}
