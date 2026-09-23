<?php

declare(strict_types=1);

namespace App\Action\Experimental;

use App\Config\Config;

/**
 * The callable and source occurrence requested by the experimental command.
 */
final readonly class InspectVariablesInput
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(
        public Config $config,
        public string $target,
        public ?int $line = null,
        public ?string $variable = null,
        public ?int $column = null,
    ) {}
}
