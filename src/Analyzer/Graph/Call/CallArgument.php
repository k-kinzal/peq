<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Call;

/**
 * An argument as supplied at a call site, without evaluating its expression.
 */
final readonly class CallArgument
{
    /**
     * The type describes only syntax whose type is certain without data-flow analysis.
     */
    public function __construct(
        public string $text,
        public ?string $name = null,
        public bool $unpack = false,
        public ?string $type = null,
    ) {}
}
