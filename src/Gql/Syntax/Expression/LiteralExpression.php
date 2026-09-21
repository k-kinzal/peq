<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Expression;

use App\Gql\Datum\Datum;
use App\Gql\Syntax\Expression;

/**
 * A value written directly into the query.
 *
 * The value is held as the value it already is rather than as the text that wrote it,
 * because reading `42` as a number is the lexer's and the parser's job and doing it
 * again at evaluation time would be doing it once too often — and would leave open
 * the possibility of the two readings disagreeing.
 */
final readonly class LiteralExpression implements Expression
{
    /**
     * @param Datum $value The value the query wrote
     */
    public function __construct(
        public Datum $value,
    ) {}
}
