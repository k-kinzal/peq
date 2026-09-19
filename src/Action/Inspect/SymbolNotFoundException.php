<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use Exception;

/**
 * Raised when the symbol asked about is not in the analyzed graph.
 *
 * The name may be misspelled, may live outside the analyzed path, or may sit in a
 * file the include and exclude patterns filtered out. None of those are programmer
 * errors, so this is a checked exception: a caller has to decide what to tell the
 * user rather than letting it escape.
 */
final class SymbolNotFoundException extends Exception
{
    /**
     * Builds the exception for a symbol name that resolved to nothing.
     *
     * @param string $target The symbol name that was asked about
     *
     * @return self An exception naming the symbol and why it may be absent
     */
    public static function forTarget(string $target): self
    {
        return new self(sprintf(
            'Symbol "%s" is not in the dependency graph. Check the spelling, the analyzed path, and the include and exclude patterns.',
            $target,
        ));
    }
}
