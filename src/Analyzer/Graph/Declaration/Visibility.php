<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Declaration;

/**
 * How widely a declaration may be named from other code.
 *
 * Visibility is the first thing an impact question asks about a member. A private
 * method can only be reached from inside the class that declares it, so changing it
 * cannot break anything the analysis did not already see; a public one can be reached
 * by code that was never analysed at all. Naming the three levels as a closed type is
 * what lets a query ask for "the public methods of this controller" without having to
 * know how PHP spells a modifier.
 *
 * A declaration that carries no visibility — a function, a class — has none of these
 * rather than a fourth case, because "not applicable" is the absence of a value and
 * not a value of its own.
 */
enum Visibility: string
{
    /** Reachable from anywhere */
    case Public = 'public';

    /** Reachable from the declaring class-like and those that inherit from it */
    case Protected = 'protected';

    /** Reachable only from the declaring class-like */
    case Private = 'private';
}
