<?php

declare(strict_types=1);

namespace App\Gql\Syntax;

/**
 * One step of a query, taking a table of rows and producing another.
 *
 * GQL queries are pipelines. `MATCH` turns one row into every way the graph matches a
 * pattern; `LET` adds a column; `FILTER` drops rows; `ORDER BY` reorders them;
 * `RETURN` decides what the reader sees. Every step has the same shape — rows in,
 * rows out — which is what makes a query readable in the order it is written.
 *
 * The interface carries nothing, for the same reason expressions carry nothing:
 * running a clause is the business of whatever runs it.
 */
interface Clause {}
