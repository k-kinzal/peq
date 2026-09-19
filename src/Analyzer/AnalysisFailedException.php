<?php

declare(strict_types=1);

namespace App\Analyzer;

use RuntimeException;

/**
 * Raised when an analyzer cannot produce a graph at all.
 *
 * This is an environment failure rather than a programmer error or a bad request:
 * a parser that could not be built, an analysis backend that is not loadable, a
 * source tree that cannot be read. A caller can report it, but there is nothing it
 * can do about it, so it belongs to the unchecked RuntimeException family and needs
 * no handler on the way out.
 */
final class AnalysisFailedException extends RuntimeException {}
