<?php

declare(strict_types=1);

namespace App\Gql;

use Exception;

/**
 * A condition a GQL query ran into, reported the way GQL reports one.
 *
 * Every failure the language can have — a query that cannot be read, a name that is
 * not bound, a value of the wrong type — is reported as one type carrying a
 * GQLSTATUS. That is deliberate: the caller of a query engine does not want to
 * distinguish failures by catching different classes, it wants to report the status
 * the standard defines and let the reader decide. One class with a code is exactly
 * that, and it keeps the boundary between peq and its query language a single
 * `catch`.
 *
 * Where a query is at fault, the position is carried too, so the reader is told which
 * part of what they wrote is the part to look at.
 */
final class GqlException extends Exception
{
    /**
     * @param StatusCode  $status      The GQLSTATUS the condition is reported under
     * @param string      $reason      What went wrong, in one sentence
     * @param null|int    $queryLine   The line of the query it happened at, or null when it is not about a place
     * @param null|int    $queryColumn The column of the query it happened at, or null when it is not about a place
     * @param null|string $context     The text of the query at that place, or null when there is none to show
     */
    public function __construct(
        public readonly StatusCode $status,
        public readonly string $reason,
        public readonly ?int $queryLine = null,
        public readonly ?int $queryColumn = null,
        public readonly ?string $context = null,
    ) {
        parent::__construct(self::describe($status, $reason, $queryLine, $queryColumn, $context));
    }

    /**
     * Reports that the query could not be read as GQL at a given place.
     *
     * @param string $reason What was expected and what was written instead
     * @param int    $line   The line it happened at
     * @param int    $column The column it happened at
     * @param string $found  The text that was written there
     *
     * @example A query that stops short says where it stopped
     *     \App\Gql\GqlException::syntax('expected a pattern', 1, 7, '<end of query>')->status // => \App\Gql\StatusCode::SyntaxError
     *
     * @return self The condition, ready to be thrown
     */
    public static function syntax(string $reason, int $line, int $column, string $found): self
    {
        return new self(StatusCode::SyntaxError, $reason, $line, $column, $found);
    }

    /**
     * Reports a condition that is about the query as a whole rather than a place in it.
     *
     * @param StatusCode $status The GQLSTATUS the condition is reported under
     * @param string     $reason What went wrong, in one sentence
     *
     * @example A name that is not bound is reported against the query, not a place
     *     \App\Gql\GqlException::because(\App\Gql\StatusCode::InvalidReference, 'p is not bound here')->queryLine // => null
     *
     * @return self The condition, ready to be thrown
     */
    public static function because(StatusCode $status, string $reason): self
    {
        return new self($status, $reason);
    }

    /**
     * Writes the condition out the way a reader is shown it.
     *
     * The code comes first because it is the part that is promised, the standard's
     * own wording for it second, and what this particular query did wrong last.
     *
     * @param StatusCode  $status      The GQLSTATUS the condition is reported under
     * @param string      $reason      What went wrong, in one sentence
     * @param null|int    $queryLine   The line it happened at, if it is about a place
     * @param null|int    $queryColumn The column it happened at, if it is about a place
     * @param null|string $context     The text written there, if there is any to show
     *
     * @example A condition reads as its code, its standard wording and its own reason
     *     \App\Gql\GqlException::describe(\App\Gql\StatusCode::InvalidReference, 'p is not bound here') // => '[42002] error: invalid reference: p is not bound here'
     *
     * @return string The one-line description
     */
    public static function describe(StatusCode $status, string $reason, ?int $queryLine = null, ?int $queryColumn = null, ?string $context = null): string
    {
        $written = sprintf('[%s] %s: %s', $status->value, $status->condition(), $reason);
        if ($queryLine !== null && $queryColumn !== null) {
            $written .= sprintf(' at line %d, column %d', $queryLine, $queryColumn);
        }

        return $context === null ? $written : $written.sprintf(' (found %s)', $context);
    }
}
