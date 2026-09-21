<?php

declare(strict_types=1);

namespace App\Gql\Syntax;

/**
 * Something a query computes a value from.
 *
 * Expressions are where GQL is most like SQL: literals, arithmetic, comparison,
 * three-valued logic, string predicates, conditionals and function calls, over values
 * that happen to include nodes, edges and paths. They appear wherever a value is
 * wanted — inside a pattern, in a filter, in what a query returns — which is why they
 * are a type of their own rather than part of any one clause.
 *
 * The interface carries nothing. An expression is a shape, and what is done with the
 * shape — evaluating it, naming it in a result heading — belongs to whoever does it,
 * not to the shape itself. Keeping it that way is what lets evaluation be written
 * once over a closed set of shapes rather than spread across them.
 */
interface Expression {}
