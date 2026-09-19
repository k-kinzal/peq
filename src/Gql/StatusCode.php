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
 * The codes follow the standard's own classes: `00` succeeded, `02` succeeded with
 * no rows, `22` the data was wrong for the operation, `42` the query was.
 */
enum StatusCode: string
{
    /** The query ran and produced at least one row */
    case Success = '00000';

    /** The query ran and produced no rows */
    case NoData = '02000';

    /** A value was outside the range its operation accepts */
    case OutOfRange = '22003';

    /** A division by zero was attempted */
    case DivisionByZero = '22012';

    /** A value was of a type the operation cannot accept */
    case InvalidType = '22G03';

    /** The query could not be read as GQL */
    case SyntaxError = '42001';

    /** The query named something that is not bound where it names it */
    case InvalidReference = '42002';

    /** The query named a function or an option that does not exist */
    case UnknownFeature = '42003';

    /**
     * Names the condition the code stands for, in the wording GQL uses.
     *
     * The standard pairs every code with a fixed phrase, and reporting that phrase
     * rather than an invented one is what makes two implementations of GQL
     * recognisable as the same language when they fail.
     *
     * @example A syntax error is reported under the standard's own wording
     *     \App\Gql\StatusCode::SyntaxError->condition() // => 'error: syntax error'
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
            self::OutOfRange => 'error: data exception - numeric value out of range',
            self::DivisionByZero => 'error: data exception - division by zero',
            self::InvalidType => 'error: data exception - invalid value type',
            self::SyntaxError => 'error: syntax error',
            self::InvalidReference => 'error: invalid reference',
            self::UnknownFeature => 'error: unsupported feature',
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

            self::OutOfRange,
            self::DivisionByZero,
            self::InvalidType,
            self::SyntaxError,
            self::InvalidReference,
            self::UnknownFeature => false,
        };
    }
}
