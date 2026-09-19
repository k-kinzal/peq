<?php

declare(strict_types=1);

namespace App\Gql\Result;

/**
 * One column of a result: what it is called and what kind of thing it holds.
 *
 * GQL says a result table carries the name and type of each of its columns, and for
 * the reader peq is built for that second part earns its keep. An agent that gets a
 * column back knows from its type whether it can compare it, sort by it or take it
 * apart, without having to look at a value and guess.
 *
 * A column whose values are not all of one kind is honest about it rather than
 * reporting the kind of the first one. That happens on a graph of source code more
 * often than it would in a database: a property like `value` is a number on one
 * symbol and a string on another.
 */
final class ResultColumn
{
    /**
     * @param string $heading What the column is called
     * @param string $type    The GQL name of what it holds
     */
    public function __construct(
        public readonly string $heading,
        public readonly string $type,
    ) {}
}
