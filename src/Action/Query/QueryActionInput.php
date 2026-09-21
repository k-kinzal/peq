<?php

declare(strict_types=1);

namespace App\Action\Query;

use App\Config\Config;

/**
 * What a query is asked to do.
 *
 * The query arrives as the text the reader wrote, because reading it is part of the
 * work: a query that cannot be read is a failure with a place in it, and the place is
 * only meaningful against the text.
 */
final readonly class QueryActionInput
{
    /**
     * @param Config $config The merged application configuration
     * @param string $query  The GQL query, as it was written
     */
    public function __construct(
        public Config $config,
        public string $query,
    ) {}
}
