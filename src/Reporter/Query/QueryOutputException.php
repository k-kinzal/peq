<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use RuntimeException;

/**
 * The query succeeded, but its result cannot be drawn in the requested format.
 *
 * This is an output failure, not a GQL condition: the same result can still be
 * written as a table or JSON without changing the query.
 */
final class QueryOutputException extends RuntimeException {}
