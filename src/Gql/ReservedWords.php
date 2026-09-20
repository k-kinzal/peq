<?php

declare(strict_types=1);

namespace App\Gql;

/**
 * Every word GQL reserves, and the rule that makes reserving them matter.
 *
 * ISO/IEC 39075 writes a regular identifier and a keyword out of the same characters
 * and tells the two apart with a single syntax rule: a regular identifier is not a
 * reserved word. Without that rule `MATCH (match)` has two readings and a grammar has
 * to guess between them; with it, a query means one thing.
 *
 * The price is that a reserved word cannot be a name written plainly. GQL's own answer
 * is the delimited identifier — `value` in back quotes is a name whatever it spells —
 * so nothing becomes unsayable, only differently said. That is why this list narrows
 * what peq reads rather than what a query can ask about.
 *
 * The standard lists the words in two productions and reserves both: `<reserved word>`,
 * and the `<pre-reserved word>`s held back for editions to come. The second is why
 * `Function` needs its back quotes although nothing in GQL uses the word yet.
 *
 * What follows is those two productions of ISO's published grammar artifact, in the
 * order the artifact writes them. `spec/features/lexical-elements.feature` fails the
 * build if it ever stops being exactly that.
 *
 * @see https://www.iso.org/standard/76120.html ISO/IEC 39075:2024, subclause 21.1
 *
 * @visibility App\Gql
 */
final class ReservedWords
{
    /**
     * The words, as `<reserved word>` and `<pre-reserved word>` list them.
     *
     * @var list<string> The reserved words
     */
    public const WORDS = [
        'ABS',
        'ACOS',
        'ALL',
        'ALL_DIFFERENT',
        'AND',
        'ANY',
        'ARRAY',
        'AS',
        'ASC',
        'ASCENDING',
        'ASIN',
        'AT',
        'ATAN',
        'AVG',
        'BIG',
        'BIGINT',
        'BINARY',
        'BOOL',
        'BOOLEAN',
        'BOTH',
        'BTRIM',
        'BY',
        'BYTE_LENGTH',
        'BYTES',
        'CALL',
        'CARDINALITY',
        'CASE',
        'CAST',
        'CEIL',
        'CEILING',
        'CHAR',
        'CHAR_LENGTH',
        'CHARACTER_LENGTH',
        'CHARACTERISTICS',
        'CLOSE',
        'COALESCE',
        'COLLECT_LIST',
        'COMMIT',
        'COPY',
        'COS',
        'COSH',
        'COT',
        'COUNT',
        'CREATE',
        'CURRENT_DATE',
        'CURRENT_GRAPH',
        'CURRENT_PROPERTY_GRAPH',
        'CURRENT_SCHEMA',
        'CURRENT_TIME',
        'CURRENT_TIMESTAMP',
        'DATE',
        'DATETIME',
        'DAY',
        'DEC',
        'DECIMAL',
        'DEGREES',
        'DELETE',
        'DESC',
        'DESCENDING',
        'DETACH',
        'DISTINCT',
        'DOUBLE',
        'DROP',
        'DURATION',
        'DURATION_BETWEEN',
        'ELEMENT_ID',
        'ELSE',
        'END',
        'EXCEPT',
        'EXISTS',
        'EXP',
        'FALSE',
        'FILTER',
        'FINISH',
        'FLOAT',
        'FLOAT16',
        'FLOAT32',
        'FLOAT64',
        'FLOAT128',
        'FLOAT256',
        'FLOOR',
        'FOR',
        'FROM',
        'GROUP',
        'HAVING',
        'HOME_GRAPH',
        'HOME_PROPERTY_GRAPH',
        'HOME_SCHEMA',
        'HOUR',
        'IF',
        'IMPLIES',
        'IN',
        'INSERT',
        'INT',
        'INTEGER',
        'INT8',
        'INTEGER8',
        'INT16',
        'INTEGER16',
        'INT32',
        'INTEGER32',
        'INT64',
        'INTEGER64',
        'INT128',
        'INTEGER128',
        'INT256',
        'INTEGER256',
        'INTERSECT',
        'INTERVAL',
        'IS',
        'LEADING',
        'LEFT',
        'LET',
        'LIKE',
        'LIMIT',
        'LIST',
        'LN',
        'LOCAL',
        'LOCAL_DATETIME',
        'LOCAL_TIME',
        'LOCAL_TIMESTAMP',
        'LOG',
        'LOG10',
        'LOWER',
        'LTRIM',
        'MATCH',
        'MAX',
        'MIN',
        'MINUTE',
        'MOD',
        'MONTH',
        'NEXT',
        'NODETACH',
        'NORMALIZE',
        'NOT',
        'NOTHING',
        'NULL',
        'NULLS',
        'NULLIF',
        'OCTET_LENGTH',
        'OF',
        'OFFSET',
        'OPTIONAL',
        'OR',
        'ORDER',
        'OTHERWISE',
        'PARAMETER',
        'PARAMETERS',
        'PATH',
        'PATH_LENGTH',
        'PATHS',
        'PERCENTILE_CONT',
        'PERCENTILE_DISC',
        'POWER',
        'PRECISION',
        'PROPERTY_EXISTS',
        'RADIANS',
        'REAL',
        'RECORD',
        'REMOVE',
        'REPLACE',
        'RESET',
        'RETURN',
        'RIGHT',
        'ROLLBACK',
        'RTRIM',
        'SAME',
        'SCHEMA',
        'SECOND',
        'SELECT',
        'SESSION',
        'SESSION_USER',
        'SET',
        'SIGNED',
        'SIN',
        'SINH',
        'SIZE',
        'SKIP',
        'SMALL',
        'SMALLINT',
        'SQRT',
        'START',
        'STDDEV_POP',
        'STDDEV_SAMP',
        'STRING',
        'SUM',
        'TAN',
        'TANH',
        'THEN',
        'TIME',
        'TIMESTAMP',
        'TRAILING',
        'TRIM',
        'TRUE',
        'TYPED',
        'UBIGINT',
        'UINT',
        'UINT8',
        'UINT16',
        'UINT32',
        'UINT64',
        'UINT128',
        'UINT256',
        'UNION',
        'UNKNOWN',
        'UNSIGNED',
        'UPPER',
        'USE',
        'USMALLINT',
        'VALUE',
        'VARBINARY',
        'VARCHAR',
        'VARIABLE',
        'WHEN',
        'WHERE',
        'WITH',
        'XOR',
        'YEAR',
        'YIELD',
        'ZONED',
        'ZONED_DATETIME',
        'ZONED_TIME',
        'ABSTRACT',
        'AGGREGATE',
        'AGGREGATES',
        'ALTER',
        'CATALOG',
        'CLEAR',
        'CLONE',
        'CONSTRAINT',
        'CURRENT_ROLE',
        'CURRENT_USER',
        'DATA',
        'DIRECTORY',
        'DRYRUN',
        'EXACT',
        'EXISTING',
        'FUNCTION',
        'GQLSTATUS',
        'GRANT',
        'INSTANT',
        'INFINITY',
        'NUMBER',
        'NUMERIC',
        'ON',
        'OPEN',
        'PARTITION',
        'PROCEDURE',
        'PRODUCT',
        'PROJECT',
        'QUERY',
        'RECORDS',
        'REFERENCE',
        'RENAME',
        'REVOKE',
        'SUBSTRING',
        'SYSTEM_USER',
        'TEMPORAL',
        'UNIQUE',
        'UNIT',
        'VALUES',
        'WHITESPACE',
    ];

    /**
     * Reports whether a word is one GQL reserves.
     *
     * A reserved word is recognised however it is cased, since GQL compares a regular
     * identifier against a keyword without regard to case.
     *
     * @param string $word The word, as it was written
     *
     * @example A word the grammar uses is reserved
     *     \App\Gql\ReservedWords::reserves('MATCH') // => true
     * @example However it is cased
     *     \App\Gql\ReservedWords::reserves('match') // => true
     * @example A word held back for a later edition is reserved now
     *     \App\Gql\ReservedWords::reserves('function') // => true
     * @example Anything else is a name
     *     \App\Gql\ReservedWords::reserves('firstName') // => false
     *
     * @return bool True when GQL reserves it
     */
    public static function reserves(string $word): bool
    {
        return in_array(strtoupper($word), self::WORDS, true);
    }

    /**
     * Returns a name the way a query has to write it.
     *
     * A graph is named by whoever wrote the code it was read from, and GQL's reserved
     * words were not consulted. Anything peq tells a reader a label or a property is
     * called therefore has to come back as something they can paste into a query, or
     * the answer to "what can I ask about?" is a query that does not parse.
     *
     * @param string $name The name, as the graph carries it
     *
     * @example A name GQL leaves free is written as it is
     *     \App\Gql\ReservedWords::asWritten('Method') // => 'Method'
     * @example A name GQL reserves is written in back quotes
     *     \App\Gql\ReservedWords::asWritten('Function') // => '`Function`'
     *
     * @return string The name, as an identifier
     */
    public static function asWritten(string $name): string
    {
        return self::reserves($name) ? '`'.$name.'`' : $name;
    }
}
