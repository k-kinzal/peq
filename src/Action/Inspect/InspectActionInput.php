<?php

declare(strict_types=1);

namespace App\Action\Inspect;

use App\Config\Config;

/**
 * Data transfer object for the input of the InspectAction.
 */
final class InspectActionInput
{
    /**
     * @param Config $config the application configuration
     * @param string $target the target to inspect
     */
    public function __construct(
        public readonly Config $config,
        public readonly string $target,
    ) {}
}
