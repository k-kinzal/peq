<?php

declare(strict_types=1);

namespace App\Gql;

/**
 * The five-character GQLSTATUS a query execution reports.
 *
 * GQL specifies that a result carries a status code beside its data, and that the
 * code — not the message — is the part a program is allowed to test for. Messages
 * change with the query and with the version; the code is the promise.
 *
 * That promise matters most for the reader this command exists for. An agent that
 * asks peq a question and gets a failure back has to decide whether it wrote the
 * query wrongly, asked about something that is not there, or found nothing — and
 * those need different next moves. A five-character code says which, without anyone
 * having to parse English.
 *
 * Every code here is one ISO/IEC 39075 defines. The standard publishes its conditions
 * as a digital artifact for implementers to report them by, and `composer spec` looks
 * every code up in it, so a code peq invented would fail the build rather than reach a
 * reader. A condition the standard gives no
 * subclass for is reported under its class code, which is what the class codes are
 * for: `42000` says the query was at fault without claiming to know a subcondition
 * that does not exist.
 *
 * @see https://www.iso.org/standard/76120.html ISO/IEC 39075:2024, GQL
 */
enum StatusCode: string
{
    /** The query ran and produced at least one row */
    case Success = '00000';

    /** The query ran and produced no rows */
    case NoData = '02000';

    /** A number was worked out that its type cannot hold */
    case NumericValueOutOfRange = '22003';

    /** A substring was asked for that a string cannot have */
    case SubstringError = '22011';

    /** A division by zero was attempted */
    case DivisionByZero = '22012';

    /** A value was of a type the operation cannot accept */
    case InvalidType = '22G03';

    /** Two values were compared that have no order between them */
    case ValuesNotComparable = '22G04';

    /** The query named a function, an aggregate or a statement GQL does not define here */
    case UnknownFeature = '42000';

    /** The query could not be read as GQL */
    case SyntaxError = '42001';

    /** The query named something that is not bound where it names it */
    case InvalidReference = '42002';

    /**
     * Names the condition the code stands for, in the wording GQL uses.
     *
     * The standard pairs every code with a fixed phrase, and reporting that phrase
     * rather than an invented one is what makes two implementations of GQL
     * recognisable as the same language when they fail. A code that names a subclass
     * is written as its class and its subclass joined by a dash, which is how both
     * the standard's own artifact and the implementations that follow it read.
     *
     * @example A syntax error is reported under the standard's own wording
     *     \App\Gql\StatusCode::SyntaxError->condition() // => 'error: syntax error or access rule violation - invalid syntax'
     * @example So is a query that simply found nothing
     *     \App\Gql\StatusCode::NoData->condition() // => 'note: no data'
     *
     * @return string The condition the code names
     */
    public function condition(): string
    {
        return match ($this) {
            self::Success => 'note: successful completion',
            self::NoData => 'note: no data',
            self::NumericValueOutOfRange => 'error: data exception - numeric value out of range',
            self::SubstringError => 'error: data exception - substring error',
            self::DivisionByZero => 'error: data exception - division by zero',
            self::InvalidType => 'error: data exception - invalid value type',
            self::ValuesNotComparable => 'error: data exception - values not comparable',
            self::UnknownFeature => 'error: syntax error or access rule violation',
            self::SyntaxError => 'error: syntax error or access rule violation - invalid syntax',
            self::InvalidReference => 'error: syntax error or access rule violation - invalid reference',
        };
    }

    /**
     * Reports whether the code stands for an execution that succeeded.
     *
     * Finding nothing is a success: a query that asks which controllers cache a
     * response and finds none has answered the question.
     *
     * @example Finding nothing is still finding out
     *     \App\Gql\StatusCode::NoData->succeeded() // => true
     * @example A query that could not be read is not
     *     \App\Gql\StatusCode::SyntaxError->succeeded() // => false
     *
     * @return bool True when the code is in a success class
     */
    public function succeeded(): bool
    {
        return match ($this) {
            self::Success,
            self::NoData => true,

            self::NumericValueOutOfRange,
            self::SubstringError,
            self::DivisionByZero,
            self::InvalidType,
            self::ValuesNotComparable,
            self::UnknownFeature,
            self::SyntaxError,
            self::InvalidReference => false,
        };
    }
}
