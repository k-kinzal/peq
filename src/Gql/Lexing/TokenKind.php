<?php

declare(strict_types=1);

namespace App\Gql\Lexing;

/**
 * What kind of thing one piece of a query text is.
 *
 * The kinds are deliberately few, and keywords are not among them. `MATCH` and a
 * variable called `match` are written identically, and which one a reader meant is
 * decided by where it stands — a question about grammar, not about spelling. Making
 * the lexer answer it would mean reserving every keyword everywhere, which is how a
 * query language ends up unable to talk about a property called `end`.
 *
 * A name written in backticks is a kind of its own for the same reason from the other
 * side: it is a name that is never a keyword, whatever it spells.
 */
enum TokenKind
{
    /** A bare name: a variable, a label, a property, or a word the grammar treats as a keyword */
    case Name;

    /** A name written in backticks, which is never read as a keyword */
    case QuotedName;

    /** A whole number */
    case Integer;

    /** A number with a fractional part, an exponent, or a double suffix */
    case Decimal;

    /** A character string in single or double quotes */
    case Text;

    /** An operator or a piece of punctuation */
    case Symbol;

    /** The end of the query text */
    case End;
}
