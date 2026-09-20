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
     * The PHP version the sources were read as is named when it is known, because a
     * file written for another version is one the analysis cannot read at all: its
     * symbols are absent from the graph rather than merely unreachable in it, and
     * nothing else peq prints would say so.
     *
     * @param string      $target     The symbol name that was asked about
     * @param null|string $phpVersion The PHP version the sources were read as, if one was settled
     *
     * @return self An exception naming the symbol and why it may be absent
     */
    public static function forTarget(string $target, ?string $phpVersion = null): self
    {
        if ($phpVersion === null) {
            return new self(sprintf(
                'Symbol "%s" is not in the dependency graph. Check the spelling, the analyzed path, and the include and exclude patterns.',
                $target,
            ));
        }

        return new self(sprintf(
            'Symbol "%s" is not in the dependency graph, which was read as PHP %s. Check the spelling, the analyzed path, the include and exclude patterns, and the PHP version the sources are written for.',
            $target,
            $phpVersion,
        ));
    }
}
