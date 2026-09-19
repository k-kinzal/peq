<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Config\Config;

/**
 * What an inspection is asked to do.
 *
 * The target is the symbol name as the user wrote it. Resolving it against the
 * graph is the inspection's work, so it arrives here as text and leaves as a node.
 */
final class InspectActionInput
{
    /**
     * @param Config $config The merged application configuration
     * @param string $target The fully qualified name of the symbol to inspect
     */
    public function __construct(
        public readonly Config $config,
        public readonly string $target,
    ) {}
}
