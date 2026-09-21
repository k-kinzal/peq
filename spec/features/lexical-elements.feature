@iso:21
Feature: Lexical elements and value types
  Source: ISO/IEC 39075:2024, clause 21, Lexical elements, and 18.9, <value type>.
  Every scenario cites where what it states is published: a subclause of the
  standard, a production of its grammar, and a code in its artifacts.

  @iso:21.3 @feature:GB02
  Scenario: A double minus sign begins a comment that runs to the end of the line
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <simple comment>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GB02
    Given the GQL-program:
      """
      -- the answer
      RETURN 1 AS n
      """
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.3 @feature:GB03
  Scenario: A double solidus begins a comment that runs to the end of the line
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <simple comment>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GB03
    Given the GQL-program:
      """
      // the answer
      RETURN 1 AS n
      """
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.3
  Scenario: A bracketed comment runs to its closing delimiter
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <bracketed comment>
    Given the GQL-program "/* the answer */ RETURN 1 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.3
  Scenario: The words the implementation reserves are the words the standard reserves
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <reserved word>, <pre-reserved word>
    Then the words the implementation reserves are the words ISO/IEC 39075 reserves

  @iso:21.3
  Scenario: A regular identifier is not a reserved word
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <regular identifier>, <reserved word>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (p:Method) RETURN p.value AS v"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.3
  Scenario: A word the language reserves is an identifier when it is written in back quotes
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <delimited identifier>
    Given the GQL-program "MATCH (p:Method) RETURN p.`value` AS v"
    When the program is read
    Then the program is accepted

  @iso:21.3
  Scenario: A delimited identifier may be written in double quotes as well
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <delimited identifier>, <double quoted character sequence>
    Given the GQL-program:
      """
      MATCH (p:"Method") RETURN count(*) AS matched
      """
    When the program is executed
    Then the result table holds one row "3"

  @iso:21.3
  Scenario: A label that spells a word the language reserves is written in back quotes
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <delimited identifier>
    Given the GQL-program "MATCH (p:`Function`) RETURN count(*) AS matched"
    When the program is read
    Then the program is accepted

  @iso:21.3
  Scenario: A word the language holds back for a later edition is reserved now
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <pre-reserved word>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (p:Function) RETURN count(*) AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.1 @iso:21.3
  Scenario: A binding variable is a regular identifier, so it is never a reserved word
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.1, 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <binding variable>, <regular identifier>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (value:Method) RETURN value.name AS n"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.1 @iso:21.3
  Scenario: A binding variable is a regular identifier, so back quotes do not make it one
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.1, 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <binding variable>, <regular identifier>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (`value`:Method) RETURN count(*) AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.3
  Scenario: A function is called by a keyword, so a reserved word names one
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.3, 20.24
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <fold>
    Given the GQL-program "RETURN upper('a') AS shouted"
    When the program is executed
    Then the result table holds one row "A"

  @iso:21.2 @feature:GV12
  Scenario: An unsigned decimal integer is an exact number
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <unsigned integer>, <unsigned decimal integer>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV12
    Given the GQL-program "RETURN 42 AS n"
    When the program is executed
    Then the result table has columns "n:INT64"
    And the result table holds one row "42"

  @iso:21.2 @feature:GV12
  Scenario: The digits of an integer may be grouped with underscores
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <unsigned decimal integer>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV12
    Given the GQL-program "RETURN 1_000_000 AS n"
    When the program is executed
    Then the result table holds one row "1000000"

  @iso:21.2 @feature:GL04 @feature:GV17
  Scenario: A number written with a point and no suffix is an exact number
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <exact numeric literal>, <unsigned decimal in common notation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL04, GV17
    Given the GQL-program "RETURN 1.5 AS n"
    When the program is executed
    Then the result table has columns "n:DECIMAL"
    And the result table holds one row "1.5"

  @iso:21.2 @feature:GL04 @feature:GV17
  Scenario: The point of an exact number may come first
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <unsigned decimal in common notation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL04, GV17
    Given the GQL-program "RETURN .5 AS n"
    When the program is executed
    Then the result table holds one row "0.5"

  @iso:21.2 @feature:GL05
  Scenario: An exact number may be marked exact with M
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <exact numeric literal>, <exact number suffix>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL05
    Given the GQL-program "RETURN 15M AS n"
    When the program is executed
    Then the result table has columns "n:INT64"
    And the result table holds one row "15"

  @iso:21.2 @feature:GL06
  Scenario: A number in scientific notation marked with M is exact
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <exact numeric literal>, <unsigned decimal in scientific notation>, <exact number suffix>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL06
    Given the GQL-program "RETURN 1.5e1M AS n"
    When the program is executed
    Then the result table has columns "n:INT64"
    And the result table holds one row "15"

  @iso:21.2 @feature:GV24
  Scenario: A number written in scientific notation is an approximate number
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <approximate numeric literal>, <unsigned decimal in scientific notation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV24
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID079
    Given the GQL-program "RETURN 1e3 AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "1000.0"

  @iso:21.2 @feature:GL07 @feature:GL09
  Scenario: A number marked with F is approximate however it is written
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <approximate numeric literal>, <approximate number suffix>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL07, GL09
    Given the GQL-program "RETURN 1.5f AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "1.5"

  @iso:21.2 @feature:GL07 @feature:GL10
  Scenario: A whole number marked with D is approximate
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <approximate numeric literal>, <approximate number suffix>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL07, GL10
    Given the GQL-program "RETURN 2d AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "2.0"

  @iso:21.2 @feature:GL08 @feature:GL09
  Scenario: A number in scientific notation may carry an approximate suffix as well
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <approximate numeric literal>, <unsigned decimal in scientific notation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL08, GL09
    Given the GQL-program "RETURN 1.5e0f AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "1.5"

  @iso:21.2
  Scenario: A character string may be written between single quotes
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <character string literal>, <single quoted character sequence>
    Given the GQL-program "RETURN 'hello' AS greeting"
    When the program is executed
    Then the result table has columns "greeting:STRING"
    And the result table holds one row "hello"

  @iso:21.2
  Scenario: A character string may be written between double quotes
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <character string literal>, <double quoted character sequence>
    Given the GQL-program:
      """
      RETURN "hello" AS greeting
      """
    When the program is executed
    Then the result table holds one row "hello"

  @iso:21.2
  Scenario: A quote written twice inside a string stands for one of itself
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <double single quote>
    Given the GQL-program "RETURN 'it''s' AS written"
    When the program is executed
    Then the result table holds one row "it's"

  @iso:21.2
  Scenario: A string may escape a character with a backslash
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <escaped character>, <unicode escape value>
    Given the GQL-program "RETURN 'a\tb' = 'a\u0009b' AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:21.2
  Scenario: A string may name a character by its code point
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <unicode escape value>, <unicode 4 digit escape value>
    Given the GQL-program "RETURN 'café' AS written"
    When the program is executed
    Then the result table holds one row "café"

  @iso:21.2
  Scenario: A backslash before a character GQL does not escape is refused
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <escaped character>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN 'a\qb' AS written"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.2 @feature:GL11
  Scenario: A string written after a commercial at escapes nothing
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <no escape>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GL11
    Given the GQL-program "RETURN @'a\tb' AS written, 'a\tb' = @'a\tb' AS matched"
    When the program is executed
    Then the result table holds one row "a\tb, FALSE"

  @iso:21.2 @feature:GV71
  Scenario: The null value is written as a word
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2, 18.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <null literal>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV71
    Given the GQL-program "RETURN NULL AS absent"
    When the program is executed
    Then the result table has columns "absent:NULL"
    And the result table holds one row "NULL"

  @iso:21.2
  Scenario: The two truth values are written as words
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean literal>
    Given the GQL-program "RETURN TRUE AS yes, FALSE AS no"
    When the program is executed
    Then the result table has columns "yes:BOOL, no:BOOL"
    And the result table holds one row "TRUE, FALSE"

  @iso:18.9 @iso:20.17 @feature:GV50
  Scenario: A list is written between brackets
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 18.9, 20.17
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <list value constructor>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV50
    Given the GQL-program "RETURN [1, 2, 3] AS xs"
    When the program is executed
    Then the result table has columns "xs:LIST"
    And the result table holds one row "[1, 2, 3]"

  @iso:20.15
  Scenario: A list is not indexed by a subscript, which GQL does not write
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.15
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <list value expression>, <list primary>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN [10, 20, 30][0] AS first"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:18.9 @feature:GV55
  Scenario: A path is a value a projection may carry
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 18.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path value type>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV55
    Given the GQL-program "MATCH p = (a:Method WHERE a.name = 'first')-[:methodCall]->(b) RETURN p AS walked"
    When the program is executed
    Then the result table has columns "walked:PATH"

  @iso:18.9 @iso:20.27 @feature:GV40
  Scenario: A zoned datetime is read from the form the standard writes it in
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 18.9, 20.27
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <datetime function>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV40
    Given the GQL-program "RETURN zoned_datetime('2024-01-15T10:30:00Z') AS moment"
    When the program is executed
    Then the result table holds one row "2024-01-15T10:30:00+00:00"

  @iso:21.4
  Scenario: A character the language does not write is refused
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.4
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <GQL terminal character>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN 1 # 2 AS n"
    When the program is read
    Then the GQLSTATUS is "42001"
