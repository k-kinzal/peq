<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

/**
 * Every word GQL reserves, and where each one comes from.
 *
 * A query language that a reader already knows is only worth having if it is actually
 * that language. The easy half of that claim — "every example in the specification
 * parses" — says nothing about the other half, which is that nothing *outside* the
 * specification parses either. One invented keyword and peq is a dialect that has to
 * be explained in every prompt, which is the thing the command exists to avoid.
 *
 * This is the reserved word reference transcribed, with the marking the reference
 * itself gives each word:
 *
 * - `standard` — reserved by the GQL standard.
 * - `prereserved` — reserved by the GQL standard for future use.
 * - `graph` — reserved by graph in Microsoft Fabric for its own extensions.
 *
 * The distinction matters for reporting honestly. A word peq recognises that is marked
 * `graph` is one it takes from Microsoft's GQL rather than from the ISO text, and a
 * reader deserves to be told which.
 *
 * @see https://learn.microsoft.com/fabric/graph/gql-reference-reserved-terms GQL reserved words reference
 */
final class GqlReservedWords
{
    /**
     * Every reserved word, by the marking the reference gives it.
     *
     * @var array<string, string> The origin, by word
     */
    private const WORDS = [
        'ABS'                      => 'standard',
        'ABSTRACT'                 => 'prereserved',
        'ACOS'                     => 'standard',
        'AGGREGATE'                => 'prereserved',
        'AGGREGATES'               => 'prereserved',
        'ALL'                      => 'standard',
        'ALL_DIFFERENT'            => 'standard',
        'ALTER'                    => 'prereserved',
        'AND'                      => 'standard',
        'ANY'                      => 'standard',
        'ARRAY'                    => 'standard',
        'AS'                       => 'standard',
        'ASC'                      => 'standard',
        'ASCENDING'                => 'standard',
        'ASIN'                     => 'standard',
        'AT'                       => 'standard',
        'ATAN'                     => 'standard',
        'AVG'                      => 'standard',
        'BIG'                      => 'standard',
        'BIGINT'                   => 'standard',
        'BINARY'                   => 'standard',
        'BOOL'                     => 'standard',
        'BOOLEAN'                  => 'standard',
        'BOTH'                     => 'standard',
        'BTRIM'                    => 'standard',
        'BY'                       => 'standard',
        'BYTE_LENGTH'              => 'standard',
        'BYTES'                    => 'standard',
        'CALL'                     => 'standard',
        'CARDINALITY'              => 'standard',
        'CASE'                     => 'standard',
        'CAST'                     => 'standard',
        'CATALOG'                  => 'prereserved',
        'CEIL'                     => 'standard',
        'CEILING'                  => 'standard',
        'CHAR'                     => 'standard',
        'CHAR_LENGTH'              => 'standard',
        'CHARACTER_LENGTH'         => 'standard',
        'CHARACTERISTICS'          => 'standard',
        'CLEAR'                    => 'prereserved',
        'CLONE'                    => 'prereserved',
        'CLOSE'                    => 'standard',
        'COALESCE'                 => 'standard',
        'COLLECT_LIST'             => 'standard',
        'COMMIT'                   => 'standard',
        'CONSTRAINT'               => 'prereserved',
        'CONSTRUCT'                => 'graph',
        'CONTAINS'                 => 'graph',
        'COPY'                     => 'standard',
        'COS'                      => 'standard',
        'COSH'                     => 'standard',
        'COT'                      => 'standard',
        'COUNT'                    => 'standard',
        'CREATE'                   => 'standard',
        'CURRENT_DATE'             => 'standard',
        'CURRENT_GRAPH'            => 'standard',
        'CURRENT_PROPERTY_GRAPH'   => 'standard',
        'CURRENT_ROLE'             => 'prereserved',
        'CURRENT_SCHEMA'           => 'standard',
        'CURRENT_TIME'             => 'standard',
        'CURRENT_TIMESTAMP'        => 'standard',
        'CURRENT_USER'             => 'prereserved',
        'DATA'                     => 'prereserved',
        'DATE'                     => 'standard',
        'DATETIME'                 => 'standard',
        'DEC'                      => 'standard',
        'DECIMAL'                  => 'standard',
        'DECLARE'                  => 'graph',
        'DEGREES'                  => 'standard',
        'DELETE'                   => 'standard',
        'DESC'                     => 'standard',
        'DESCENDING'               => 'standard',
        'DETACH'                   => 'standard',
        'DIRECTORY'                => 'prereserved',
        'DISTINCT'                 => 'standard',
        'DOUBLE'                   => 'standard',
        'DROP'                     => 'standard',
        'DRYRUN'                   => 'prereserved',
        'DURATION'                 => 'standard',
        'DURATION_BETWEEN'         => 'standard',
        'EDGE'                     => 'graph',
        'EDGES'                    => 'graph',
        'ELEMENT'                  => 'graph',
        'ELEMENTS'                 => 'graph',
        'ELEMENT_ID'               => 'standard',
        'ELSE'                     => 'standard',
        'END'                      => 'standard',
        'ENDS'                     => 'graph',
        'ENUM'                     => 'graph',
        'EXACT'                    => 'graph',
        'EXCEPT'                   => 'standard',
        'EXISTING'                 => 'prereserved',
        'EXISTS'                   => 'standard',
        'EXP'                      => 'standard',
        'FILTER'                   => 'standard',
        'FINISH'                   => 'standard',
        'FLOAT'                    => 'standard',
        'FLOAT16'                  => 'standard',
        'FLOAT32'                  => 'standard',
        'FLOAT64'                  => 'standard',
        'FLOAT128'                 => 'standard',
        'FLOAT256'                 => 'standard',
        'FLOOR'                    => 'standard',
        'FOR'                      => 'standard',
        'FROM'                     => 'standard',
        'FULLTEXT'                 => 'graph',
        'FUNCTION'                 => 'prereserved',
        'GQL'                      => 'graph',
        'GQLSTATUS'                => 'prereserved',
        'GRANT'                    => 'prereserved',
        'GROUP'                    => 'standard',
        'HAVING'                   => 'standard',
        'HOME_GRAPH'               => 'standard',
        'HOME_PROPERTY_GRAPH'      => 'standard',
        'HOME_SCHEMA'              => 'standard',
        'IF'                       => 'standard',
        'IN'                       => 'standard',
        'INCLUDE'                  => 'graph',
        'INDEX'                    => 'graph',
        'INFINITY'                 => 'prereserved',
        'INSERT'                   => 'standard',
        'INSTANT'                  => 'prereserved',
        'INT'                      => 'standard',
        'INTEGER'                  => 'standard',
        'INT8'                     => 'standard',
        'INTEGER8'                 => 'standard',
        'INT16'                    => 'standard',
        'INTEGER16'                => 'standard',
        'INT32'                    => 'standard',
        'INTEGER32'                => 'standard',
        'INT64'                    => 'standard',
        'INTEGER64'                => 'standard',
        'INT96'                    => 'graph',
        'INTEGER96'                => 'graph',
        'INT128'                   => 'standard',
        'INTEGER128'               => 'standard',
        'INT256'                   => 'standard',
        'INTEGER256'               => 'standard',
        'INTERSECT'                => 'standard',
        'INTERVAL'                 => 'standard',
        'IS'                       => 'standard',
        'JSON'                     => 'graph',
        'KEY'                      => 'graph',
        'KEYS'                     => 'graph',
        'LABEL'                    => 'graph',
        'LABELS'                   => 'graph',
        'LEADING'                  => 'standard',
        'LEFT'                     => 'standard',
        'LET'                      => 'standard',
        'LIKE'                     => 'standard',
        'LIMIT'                    => 'standard',
        'LIST'                     => 'standard',
        'LN'                       => 'standard',
        'LOCAL'                    => 'standard',
        'LOCAL_DATETIME'           => 'standard',
        'LOCAL_TIME'               => 'standard',
        'LOCAL_TIMESTAMP'          => 'standard',
        'LOG'                      => 'standard',
        'LOG10'                    => 'standard',
        'LOWER'                    => 'standard',
        'LTRIM'                    => 'standard',
        'MATCH'                    => 'standard',
        'MAX'                      => 'standard',
        'MERGE'                    => 'graph',
        'MICROSOFT'                => 'graph',
        'MIN'                      => 'standard',
        'MOD'                      => 'standard',
        'MSFTGQL'                  => 'graph',
        'NEXT'                     => 'standard',
        'NODE'                     => 'graph',
        'NODES'                    => 'graph',
        'NODETACH'                 => 'standard',
        'NORMALIZE'                => 'standard',
        'NOT'                      => 'standard',
        'NOTHING'                  => 'standard',
        'NULL'                     => 'standard',
        'NULLIF'                   => 'standard',
        'NULLS'                    => 'standard',
        'NUMBER'                   => 'prereserved',
        'NUMERIC'                  => 'prereserved',
        'OCTET_LENGTH'             => 'standard',
        'OF'                       => 'standard',
        'OFFSET'                   => 'standard',
        'ON'                       => 'prereserved',
        'OPEN'                     => 'prereserved',
        'OPTIONAL'                 => 'standard',
        'OR'                       => 'standard',
        'ORDER'                    => 'standard',
        'OTHERWISE'                => 'standard',
        'PARAMETER'                => 'standard',
        'PARAMETERS'               => 'standard',
        'PARTITION'                => 'prereserved',
        'PATH'                     => 'standard',
        'PATH_LENGTH'              => 'standard',
        'PATHS'                    => 'standard',
        'PERCENTILE_CONT'          => 'standard',
        'PERCENTILE_DISC'          => 'standard',
        'POINT'                    => 'graph',
        'POWER'                    => 'standard',
        'PRAGMA'                   => 'graph',
        'PRECISION'                => 'standard',
        'PREPARE'                  => 'graph',
        'PROCEDURE'                => 'prereserved',
        'PRODUCT'                  => 'prereserved',
        'PROJECT'                  => 'prereserved',
        'PROPERTY_EXISTS'          => 'standard',
        'QUERY'                    => 'prereserved',
        'RADIANS'                  => 'standard',
        'RANGE'                    => 'graph',
        'REAL'                     => 'standard',
        'RECORD'                   => 'standard',
        'RECORDS'                  => 'prereserved',
        'REFERENCE'                => 'prereserved',
        'REGEXP_CONTAINS'          => 'graph',
        'RELATIONSHIP'             => 'graph',
        'RELATIONSHIPS'            => 'graph',
        'REMOVE'                   => 'standard',
        'RENAME'                   => 'prereserved',
        'REPLACE'                  => 'standard',
        'RESET'                    => 'standard',
        'RETURN'                   => 'standard',
        'REVOKE'                   => 'prereserved',
        'RIGHT'                    => 'standard',
        'ROLLBACK'                 => 'standard',
        'RTRIM'                    => 'standard',
        'SAME'                     => 'standard',
        'SCHEMA'                   => 'standard',
        'SELECT'                   => 'standard',
        'SESSION'                  => 'standard',
        'SESSION_USER'             => 'standard',
        'SET'                      => 'standard',
        'SHOW'                     => 'graph',
        'SIGNED'                   => 'standard',
        'SIN'                      => 'standard',
        'SINH'                     => 'standard',
        'SIZE'                     => 'standard',
        'SKIP'                     => 'standard',
        'SMALL'                    => 'standard',
        'SMALLINT'                 => 'standard',
        'SQRT'                     => 'standard',
        'START'                    => 'standard',
        'STARTS'                   => 'graph',
        'STDDEV_POP'               => 'standard',
        'STDDEV_SAMP'              => 'standard',
        'STRING'                   => 'standard',
        'STRING_JOIN'              => 'graph',
        'SUBSTRING'                => 'prereserved',
        'SUM'                      => 'standard',
        'SYSTEM_USER'              => 'prereserved',
        'TAN'                      => 'standard',
        'TANH'                     => 'standard',
        'TEMPORAL'                 => 'prereserved',
        'TEXT'                     => 'graph',
        'THEN'                     => 'standard',
        'TIME'                     => 'standard',
        'TIMESTAMP'                => 'standard',
        'TO_JSON'                  => 'graph',
        'TO_JSON_STRING'           => 'graph',
        'TRAILING'                 => 'standard',
        'TRIM'                     => 'standard',
        'TYPED'                    => 'standard',
        'UBIGINT'                  => 'standard',
        'UINT'                     => 'standard',
        'UINT8'                    => 'standard',
        'UINT16'                   => 'standard',
        'UINT32'                   => 'standard',
        'UINT64'                   => 'standard',
        'UINT128'                  => 'standard',
        'UINT256'                  => 'standard',
        'UNION'                    => 'standard',
        'UNIQUE'                   => 'prereserved',
        'UNIT'                     => 'prereserved',
        'UNSIGNED'                 => 'standard',
        'UPDATE'                   => 'graph',
        'UPPER'                    => 'standard',
        'USE'                      => 'standard',
        'USMALLINT'                => 'standard',
        'USING'                    => 'graph',
        'VALUE'                    => 'standard',
        'VALUES'                   => 'prereserved',
        'VARBINARY'                => 'standard',
        'VARCHAR'                  => 'standard',
        'VARIABLE'                 => 'standard',
        'VECTOR'                   => 'graph',
        'VERTEX'                   => 'graph',
        'VERTICES'                 => 'graph',
        'WHEN'                     => 'standard',
        'WHERE'                    => 'standard',
        'WITH'                     => 'standard',
        'XOR'                      => 'standard',
        'YIELD'                    => 'standard',
        'ZONED'                    => 'standard',
        'ZONED_DATETIME'           => 'standard',
        'ZONED_TIME'               => 'standard',
    ];

    /**
     * Words the language guide documents as syntax without the reference reserving them.
     *
     * The reference lists what a query may not use as an identifier, which is not
     * quite the same list as what a query may write. The path modes and the truth
     * literals are documented by the language guide as parts of the grammar and are
     * absent from the reference, so they are recorded here rather than pretended into
     * it.
     *
     * @var array<string, string> Where the guide documents it, by word
     */
    private const DOCUMENTED = [
        'WALK' => 'path mode, documented in the language guide under graph patterns',
        'TRAIL' => 'path mode, documented in the language guide under graph patterns',
        'SIMPLE' => 'path mode, documented in the language guide under graph patterns',
        'ACYCLIC' => 'path mode, documented in the language guide under graph patterns',
        'TRUE' => 'truth literal, documented in the language guide under basic value types',
        'FALSE' => 'truth literal, documented in the language guide under basic value types',
        'UNKNOWN' => 'truth literal, documented in the language guide under basic value types',
    ];

    /**
     * Reports whether GQL gives a word a meaning of its own.
     *
     * @param string $word The word, in upper case
     *
     * @return bool True when the reference reserves it or the guide documents it
     */
    public static function knows(string $word): bool
    {
        return isset(self::WORDS[$word]) || isset(self::DOCUMENTED[$word]);
    }

    /**
     * Says where GQL gets a word from.
     *
     * @param string $word The word, in upper case
     *
     * @return null|string Where it comes from, or null when GQL does not know it
     */
    public static function originOf(string $word): ?string
    {
        if (isset(self::DOCUMENTED[$word])) {
            return self::DOCUMENTED[$word];
        }

        return self::WORDS[$word] ?? null;
    }

    /**
     * Returns every word the reference reserves.
     *
     * @return list<string> The words
     */
    public static function reserved(): array
    {
        return array_keys(self::WORDS);
    }
}
