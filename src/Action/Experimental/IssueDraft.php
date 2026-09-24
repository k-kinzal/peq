<?php

declare(strict_types=1);

namespace App\Action\Experimental;

/**
 * The exact title and body previewed before any external transmission.
 */
final readonly class IssueDraft
{
    /**
     * Creates a reviewable issue without transmitting anything.
     */
    public function __construct(public string $title, public string $body) {}
}
