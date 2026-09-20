@iso:14
Feature: Composite and linear queries
  Source: ISO/IEC 39075:2024, clause 13, Composite query statements, and clause 14,
  Linear query statements. Each scenario is tagged with the subclause it states and,
  where it states an optional feature, with the feature code from
  spec/iso/features.xml.

  The subclauses stated in this feature:
    14.1    <composite query statement>
    14.2    <composite query expression>
    14.3    <linear query statement> and <simple query statement>
    14.6    <filter statement>
    14.7    <let statement>
    14.9    <order by and page statement>
    14.10   <primitive result statement>

  @iso:14.3
  Scenario: A linear query ends in the statement that says what to show
    Given the GQL-program "MATCH (m:Method)"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:14.3
  Scenario: Showing everything a query bound is written the way the language writes it
    Given the GQL-program "MATCH (m:Method WHERE m.name = 'first') RETURN *"
    When the program is executed
    Then the result table has columns "m:NODE"

  @iso:14.3
  Scenario: Each operand of a composite query says what to show for itself
    Given the GQL-program "RETURN 1 AS n UNION ALL MATCH (m:Method)"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:14.2 @feature:GQ03
  Scenario: UNION keeps the rows of both operands, dropping those that repeat
    Given the GQL-program "RETURN 1 AS n UNION RETURN 1 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:14.2
  Scenario: UNION ALL keeps the rows of both operands, repeats and all
    Given the GQL-program "RETURN 1 AS n UNION ALL RETURN 1 AS n"
    When the program is executed
    Then the result table holds:
      """
      1
      1
      """

  @iso:14.2 @feature:GQ04
  Scenario: EXCEPT keeps the rows its left operand has and its right does not
    Given the GQL-program "RETURN 1 AS n EXCEPT RETURN 2 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:14.2 @feature:GQ06
  Scenario: INTERSECT keeps the rows both operands have
    Given the GQL-program "RETURN 1 AS n INTERSECT RETURN 1 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:14.2 @feature:GQ02
  Scenario: OTHERWISE answers with its right operand only when its left found nothing
    Given the GQL-program "MATCH (p:Interface) RETURN p.name AS name OTHERWISE RETURN 'none' AS name"
    When the program is executed
    Then the result table holds one row "none"

  @iso:14.2 @feature:GQ02
  Scenario: OTHERWISE answers with its left operand when that found something
    Given the GQL-program "RETURN 'found' AS name OTHERWISE RETURN 'none' AS name"
    When the program is executed
    Then the result table holds one row "found"

  @iso:14.2
  Scenario: Two operands of different widths cannot be combined
    Given the GQL-program "RETURN 1 AS n UNION RETURN 1 AS n, 2 AS m"
    When the program is executed
    Then the GQLSTATUS is "42001"

  @iso:14.6 @feature:GQ08
  Scenario: FILTER keeps the rows its predicate is true of
    Given the GQL-program "MATCH (a:Method) FILTER a.line > 9 RETURN count(*) AS kept"
    When the program is executed
    Then the result table holds one row "2"

  @iso:14.6 @feature:GQ08
  Scenario: FILTER takes an optional WHERE that changes nothing
    Given the GQL-program "MATCH (a:Method) FILTER WHERE a.line > 9 RETURN count(*) AS kept"
    When the program is executed
    Then the result table holds one row "2"

  @iso:14.7 @feature:GQ09
  Scenario: LET binds a computed value to a name for the statements after it
    Given the GQL-program "MATCH (a:Method) LET shouted = upper(a.name) RETURN shouted AS name ORDER BY name"
    When the program is executed
    Then the result table holds:
      """
      FIRST
      SECOND
      THIRD
      """

  @iso:14.7 @feature:GQ09
  Scenario: Names bound by one LET cannot see one another
    Given the GQL-program "MATCH (a:Method) LET name = a.name, greeting = name RETURN greeting AS g"
    When the program is executed
    Then the GQLSTATUS is "42002"

  @iso:14.9 @feature:GQ13
  Scenario: LIMIT keeps the first rows and no more
    Given the GQL-program "MATCH (a:Method) ORDER BY a.name LIMIT 1 RETURN a.name AS name"
    When the program is executed
    Then the result table holds one row "first"

  @iso:14.9 @feature:GQ12
  Scenario: OFFSET drops the first rows
    Given the GQL-program "MATCH (a:Method) ORDER BY a.name OFFSET 1 LIMIT 1 RETURN a.name AS name"
    When the program is executed
    Then the result table holds one row "second"

  @iso:14.9
  Scenario: SKIP is the other way to write OFFSET
    Given the GQL-program "MATCH (a:Method) ORDER BY a.name SKIP 1 LIMIT 1 RETURN a.name AS name"
    When the program is executed
    Then the result table holds one row "second"

  @iso:14.9 @feature:GQ14
  Scenario: A sort key may be any expression, not only a binding variable
    Given the GQL-program "MATCH (a:Method) RETURN a.name AS name ORDER BY char_length(a.name), a.name"
    When the program is executed
    Then the result table holds:
      """
      first
      third
      second
      """

  @iso:14.9
  Scenario: The absence of a value sorts before every value
    Given the GQL-program "MATCH (a:ClassLike) RETURN a.name AS name ORDER BY a.visibility, a.name"
    When the program is executed
    Then the result table holds:
      """
      Base
      Ring
      """

  @iso:14.10
  Scenario: DISTINCT shows the rows that repeat once
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->(b) RETURN DISTINCT b.name AS called ORDER BY called"
    When the program is executed
    Then the result table holds:
      """
      first
      second
      third
      """

  @iso:14.10 @feature:GQ15
  Scenario: GROUP BY gathers the rows that agree on what they are grouped by
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->(b) RETURN b.name AS called, count(*) AS callers GROUP BY called ORDER BY called"
    When the program is executed
    Then the result table holds:
      """
      first, 1
      second, 2
      third, 1
      """

  @iso:14.10 @feature:GQ16
  Scenario: A sort key may name a column the same projection introduces
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->(b) RETURN b.name AS called, count(*) AS callers GROUP BY called ORDER BY callers DESC, called"
    When the program is executed
    Then the result table holds:
      """
      second, 2
      first, 1
      third, 1
      """

  @iso:14.10
  Scenario: A projection may ask for everything in scope
    Given the GQL-program "LET n = 1 LET m = 2 RETURN *"
    When the program is executed
    Then the result table has columns "n:INT64, m:INT64"
