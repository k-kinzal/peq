<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

/**
 * Every condition ISO/IEC 39075 says a GQL implementation may report.
 *
 * The standard publishes its conditions as a digital artifact whose stated purpose is
 * for implementers "to specify the natural-language text that is reported whenever any
 * of the various conditions are generated". This is that list, transcribed: the class
 * and subclass codes, the category each belongs to, and the standard's own wording.
 *
 * It is here so that peq's claim to report GQLSTATUS is checkable rather than
 * asserted. A status code peq reports that is not in this list is one peq invented,
 * and the conformance contract fails on it — which is how `42003`, a code no part of
 * the standard defines, was found after it had already shipped in a branch.
 *
 * @see https://www.iso.org/standard/76120.html ISO/IEC 39075:2024, Information technology — Database languages — GQL
 */
final class GqlConditions
{
    /**
     * The categories the standard sorts its conditions into, and how each is read out.
     *
     * A status is printed as its category and then its condition, which is the form
     * every implementation of GQL prints and the form a reader recognises: `note:
     * successful completion`, `error: data exception - division by zero`.
     */
    private const CATEGORIES = [
        'S' => 'note',
        'N' => 'note',
        'I' => 'note',
        'W' => 'warning',
        'X' => 'error',
    ];

    /**
     * Every condition the standard defines, by its five-character code.
     *
     * @var array<string, array{string, string}> The category and the condition, by code
     */
    private const CONDITIONS = [
        '00000'   => ['S', 'successful completion'],
        '00001'   => ['S', 'successful completion - omitted result'],
        '01000'   => ['W', 'warning'],
        '01004'   => ['W', 'warning - string data, right truncation'],
        '01G03'   => ['W', 'warning - graph does not exist'],
        '01G04'   => ['W', 'warning - graph type does not exist'],
        '01G11'   => ['W', 'warning - null value eliminated in set function'],
        '02000'   => ['N', 'no data'],
        '03000'   => ['I', 'informational'],
        '08000'   => ['X', 'connection exception'],
        '08007'   => ['X', 'connection exception - transaction resolution unknown'],
        '22000'   => ['X', 'data exception'],
        '22001'   => ['X', 'data exception - string data, right truncation'],
        '22003'   => ['X', 'data exception - numeric value out of range'],
        '22004'   => ['X', 'data exception - null value not allowed'],
        '22007'   => ['X', 'data exception - invalid date, time, or, datetime format'],
        '22008'   => ['X', 'data exception - datetime field overflow'],
        '22011'   => ['X', 'data exception - substring error'],
        '22012'   => ['X', 'data exception - division by zero'],
        '22015'   => ['X', 'data exception - interval field overflow'],
        '22018'   => ['X', 'data exception - invalid character value for cast'],
        '2201E'   => ['X', 'data exception - invalid argument for natural logarithm'],
        '2201F'   => ['X', 'data exception - invalid argument for power function'],
        '22027'   => ['X', 'data exception - trim error'],
        '2202F'   => ['X', 'data exception - array data, right truncation'],
        '22G02'   => ['X', 'data exception - negative limit value'],
        '22G03'   => ['X', 'data exception - invalid value type'],
        '22G04'   => ['X', 'data exception - values not comparable'],
        '22G05'   => ['X', 'data exception - invalid date, time, or datetime function field name'],
        '22G06'   => ['X', 'data exception - invalid datetime function value'],
        '22G07'   => ['X', 'data exception - invalid duration function field name'],
        '22G0B'   => ['X', 'data exception - list data, right truncation'],
        '22G0C'   => ['X', 'data exception - list element error'],
        '22G0F'   => ['X', 'data exception - invalid number of paths or groups'],
        '22G0H'   => ['X', 'data exception - invalid duration format'],
        '22G0M'   => ['X', 'data exception - multiple assignments to a graph element property'],
        '22G0N'   => ['X', 'data exception - number of node labels below supported minimum'],
        '22G0P'   => ['X', 'data exception - number of node labels exceeds supported maximum'],
        '22G0Q'   => ['X', 'data exception - number of edge labels below supported minimum'],
        '22G0R'   => ['X', 'data exception - number of edge labels exceeds supported maximum'],
        '22G0S'   => ['X', 'data exception - number of node properties exceeds supported maximum'],
        '22G0T'   => ['X', 'data exception - number of edge properties exceeds supported maximum'],
        '22G0U'   => ['X', 'data exception - record fields do not match'],
        '22G0V'   => ['X', 'data exception - reference value, invalid base type'],
        '22G0W'   => ['X', 'data exception - reference value, invalid constrained type'],
        '22G0X'   => ['X', 'data exception - record data, field unassignable'],
        '22G0Y'   => ['X', 'data exception - record data, field missing'],
        '22G0Z'   => ['X', 'data exception - malformed path'],
        '22G10'   => ['X', 'data exception - path data, right truncation'],
        '22G11'   => ['X', 'data exception - reference value, referent deleted'],
        '22G12'   => ['X', 'data exception - invalid value type'],
        '22G13'   => ['X', 'data exception - invalid group variable value'],
        '22G14'   => ['X', 'data exception - incompatible temporal instant unit groups'],
        '25000'   => ['X', 'invalid transaction state'],
        '25G01'   => ['X', 'invalid transaction state - active GQL-transaction'],
        '25G02'   => ['X', 'invalid transaction state - catalog and data statement mixing not supported'],
        '25G03'   => ['X', 'invalid transaction state - read-only GQL-transaction'],
        '25G04'   => ['X', 'invalid transaction state - accessing multiple graphs not supported'],
        '2D000'   => ['X', 'invalid transaction termination'],
        '40000'   => ['X', 'transaction rollback'],
        '40003'   => ['X', 'transaction rollback - statement completion unknown'],
        '42000'   => ['X', 'syntax error or access rule violation'],
        '42001'   => ['X', 'syntax error or access rule violation - invalid syntax'],
        '42002'   => ['X', 'syntax error or access rule violation - invalid reference'],
        '42004'   => ['X', 'syntax error or access rule violation - use of visually confusable identifiers'],
        '42006'   => ['X', 'syntax error or access rule violation - number of edge labels below supported minimum'],
        '42007'   => ['X', 'syntax error or access rule violation - number of edge labels exceeds supported maximum'],
        '42008'   => ['X', 'syntax error or access rule violation - number of edge properties exceeds supported maximum'],
        '42009'   => ['X', 'syntax error or access rule violation - number of node labels below supported minimum'],
        '42010'   => ['X', 'syntax error or access rule violation - number of node labels exceeds supported maximum'],
        '42011'   => ['X', 'syntax error or access rule violation - number of node properties exceeds supported maximum'],
        '42012'   => ['X', 'syntax error or access rule violation - number of node type key labels below supported minimum'],
        '42013'   => ['X', 'syntax error or access rule violation - number of node type key labels exceeds supported maximum'],
        '42014'   => ['X', 'syntax error or access rule violation - number of edge type key labels below supported minimum'],
        '42015'   => ['X', 'syntax error or access rule violation - number of edge type key labels exceeds supported maximum'],
        'G1000'   => ['X', 'dependent object error'],
        'G1001'   => ['X', 'dependent object error - edges still exist'],
        'G1002'   => ['X', 'dependent object error - endpoint node is deleted'],
        'G1003'   => ['X', 'dependent object error - endpoint node not in current working graph'],
        'G2000'   => ['X', 'graph type violation'],
    ];

    /**
     * Reports whether a five-character code is one the standard defines.
     *
     * @param string $code The code
     *
     * @return bool True when the standard defines it
     */
    public static function defines(string $code): bool
    {
        return isset(self::CONDITIONS[$code]);
    }

    /**
     * Returns the condition the standard pairs with a code, as it would be read out.
     *
     * @param string $code The code
     *
     * @return null|string The condition, or null when the standard defines no such code
     */
    public static function conditionOf(string $code): ?string
    {
        $found = self::CONDITIONS[$code] ?? null;

        return $found === null ? null : self::CATEGORIES[$found[0]].': '.$found[1];
    }

    /**
     * Returns every code the standard defines.
     *
     * @return list<string> The codes
     */
    public static function codes(): array
    {
        return array_keys(self::CONDITIONS);
    }
}
