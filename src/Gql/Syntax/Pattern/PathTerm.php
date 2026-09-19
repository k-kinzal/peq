<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * One piece of a path pattern.
 *
 * A path pattern is written as an alternation of things that match nodes and things
 * that match edges, and a parenthesised group of both counts as one of either. What
 * they have in common is only their place in the alternation, which is why the
 * interface says nothing: it exists so that a path can hold them in one list.
 */
interface PathTerm {}
