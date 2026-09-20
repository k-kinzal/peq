@iso:21
Feature: Lexical elements and value types
  Source: ISO/IEC 39075:2024, clause 21, Lexical elements, and clause 19, Types.
  Each scenario is tagged with the subclause it states and, where it states an
  optional feature, with the feature code from spec/iso/features.xml.

  The subclauses stated in this feature:
    18.9    <value type>
    21.2    <literal>
    21.3    <token>, <separator>, and <identifier>
    21.4    <GQL terminal character>

  @iso:21.3 @feature:GB02
  Scenario: A double minus sign begins a comment that runs to the end of the line
    Given the GQL-program:
      """
      -- the answer
      RETURN 1 AS n
      """
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.3 @feature:GB03
  Scenario: A double solidus begins a comment that runs to the end of the line
    Given the GQL-program:
      """
      // the answer
      RETURN 1 AS n
      """
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.3
  Scenario: A bracketed comment runs to its closing delimiter
    Given the GQL-program "/* the answer */ RETURN 1 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.4
  Scenario: The words the implementation reserves are the words the standard reserves
    Then the words the implementation reserves are the words ISO/IEC 39075 reserves

  @iso:21.3
  Scenario: A regular identifier is not a reserved word
    Given the GQL-program "MATCH (p:Method) RETURN p.value AS v"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.3
  Scenario: A word the language reserves is an identifier when it is written in back quotes
    Given the GQL-program "MATCH (p:Method) RETURN p.`value` AS v"
    When the program is read
    Then the program is accepted

  @iso:21.3
  Scenario: A label that spells a word the language reserves is written in back quotes
    Given the GQL-program "MATCH (p:`Function`) RETURN count(*) AS matched"
    When the program is read
    Then the program is accepted

  @iso:21.3
  Scenario: A word the language holds back for a later edition is reserved now
    Given the GQL-program "MATCH (p:Function) RETURN count(*) AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.3
  Scenario: A binding variable is a regular identifier, so it is never a reserved word
    Given the GQL-program "MATCH (value:Method) RETURN value.name AS n"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.3
  Scenario: A binding variable is a regular identifier, so back quotes do not make it one
    Given the GQL-program "MATCH (`value`:Method) RETURN count(*) AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:21.3
  Scenario: A function is called by a keyword, so a reserved word names one
    Given the GQL-program "RETURN upper('a') AS shouted"
    When the program is executed
    Then the result table holds one row "A"

  @iso:21.2 @feature:GV12
  Scenario: An unsigned decimal integer is an exact number
    Given the GQL-program "RETURN 42 AS n"
    When the program is executed
    Then the result table has columns "n:INT64"
    And the result table holds one row "42"

  @iso:21.2 @feature:GV24
  Scenario: A number written with a fractional part is an approximate number
    Given the GQL-program "RETURN 1.5 AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "1.5"

  @iso:21.2 @feature:GV24
  Scenario: A number written in scientific notation is an approximate number
    Given the GQL-program "RETURN 1e3 AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"

  @iso:21.2
  Scenario: A character string may be written between single quotes
    Given the GQL-program "RETURN 'hello' AS greeting"
    When the program is executed
    Then the result table has columns "greeting:STRING"
    And the result table holds one row "hello"

  @iso:21.2
  Scenario: A character string may be written between double quotes
    Given the GQL-program:
      """
      RETURN "hello" AS greeting
      """
    When the program is executed
    Then the result table holds one row "hello"

  @iso:21.2
  Scenario: A quote written twice inside a string stands for one of itself
    Given the GQL-program "RETURN 'it''s' AS written"
    When the program is executed
    Then the result table holds one row "it's"

  @iso:21.2 @feature:GV71
  Scenario: The null value is written as a word
    Given the GQL-program "RETURN NULL AS absent"
    When the program is executed
    Then the result table has columns "absent:NULL"
    And the result table holds one row "NULL"

  @iso:21.2
  Scenario: The two truth values are written as words
    Given the GQL-program "RETURN TRUE AS yes, FALSE AS no"
    When the program is executed
    Then the result table has columns "yes:BOOL, no:BOOL"
    And the result table holds one row "TRUE, FALSE"

  @iso:18.9 @feature:GV50
  Scenario: A list is written between brackets
    Given the GQL-program "RETURN [1, 2, 3] AS xs"
    When the program is executed
    Then the result table has columns "xs:LIST"
    And the result table holds one row "[1, 2, 3]"

  @iso:18.9 @feature:GV50
  Scenario: A list is indexed from zero
    Given the GQL-program "RETURN [10, 20, 30][0] AS first"
    When the program is executed
    Then the result table holds one row "10"

  @iso:18.9 @feature:GV55
  Scenario: A path is a value a projection may carry
    Given the GQL-program "MATCH p = (a:Method WHERE a.name = 'first')-[:methodCall]->(b) RETURN p AS walked"
    When the program is executed
    Then the result table has columns "walked:PATH"

  @iso:18.9 @feature:GV40
  Scenario: A zoned datetime is read from the form the standard writes it in
    Given the GQL-program "RETURN zoned_datetime('2024-01-15T10:30:00Z') AS moment"
    When the program is executed
    Then the result table holds one row "2024-01-15T10:30:00+00:00"

  @iso:21.4
  Scenario: A character the language does not write is refused
    Given the GQL-program "RETURN 1 # 2 AS n"
    When the program is read
    Then the GQLSTATUS is "42001"
