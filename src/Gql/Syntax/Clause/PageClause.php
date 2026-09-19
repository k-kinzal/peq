<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Clause;

use App\Gql\Syntax\Clause;

/**
 * Taking a stretch of the rows and dropping the rest.
 *
 * `OFFSET` and `LIMIT` are one clause here because they are one operation — skip
 * some, then keep some — and because GQL lets them be written together. Either may
 * be absent: written alone, `LIMIT` skips nothing and `OFFSET` keeps everything after
 * what it skipped.
 *
 * On a graph query this is a safety valve as much as a convenience. A pattern with an
 * unbounded quantifier over a large codebase can match a great many paths, and a
 * limit is how a reader asks for an answer rather than for all of them.
 */
final class PageClause implements Clause
{
    /**
     * @param int      $offset How many rows to skip
     * @param null|int $limit  How many to keep after that, or null for all of them
     */
    public function __construct(
        public readonly int $offset = 0,
        public readonly ?int $limit = null,
    ) {
        assert($this->offset >= 0, 'A page cannot skip a negative number of rows');
        assert($this->limit === null || $this->limit >= 0, 'A page cannot keep a negative number of rows');
    }
}
