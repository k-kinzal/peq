@iso:14
Feature: Composite and linear queries
  Source: ISO/IEC 39075:2024, clause 14, Query statements, with the clauses of 16
  they are written with. Every scenario cites where what it states is published: a
  subclause of the standard, a production of its grammar, and a code in its
  artifacts.

  @iso:14.3
  Scenario: A linear query ends in the statement that says what to show
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <linear query statement>, <primitive result statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (m:Method)"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:14.11
  Scenario: Showing everything a query bound is written the way the language writes it
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <return statement body>
    Given the GQL-program "MATCH (m:Method WHERE m.name = 'first') RETURN *"
    When the program is executed
    Then the result table has columns "m:NODE"

  @iso:14.2
  Scenario: Each operand of a composite query says what to show for itself
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <composite query expression>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN 1 AS n UNION ALL MATCH (m:Method)"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:14.2 @feature:GQ03
  Scenario: UNION keeps the rows of both operands, dropping those that repeat
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <query conjunction>, <set operator>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ03
    Given the GQL-program "RETURN 1 AS n UNION RETURN 1 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:14.2 @feature:GQ03
  Scenario: UNION ALL keeps the rows of both operands, repeats and all
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <set operator>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ03
    Given the GQL-program "RETURN 1 AS n UNION ALL RETURN 1 AS n"
    When the program is executed
    Then the result table holds:
      """
      1
      1
      """

  @iso:14.2 @feature:GQ03
  Scenario: A column of a union holds the values of both operands, and is typed by them
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ03
    Given the GQL-program "RETURN 1 AS n UNION ALL RETURN 'a' AS n"
    When the program is executed
    Then the result table has columns "n:ANY"
    And the result table holds:
      """
      1
      a
      """

  @iso:14.2 @feature:GQ04
  Scenario: EXCEPT keeps the rows its left operand has and its right does not
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <set operator>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ04
    Given the GQL-program "RETURN 1 AS n EXCEPT RETURN 2 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:14.2 @feature:GQ06
  Scenario: INTERSECT keeps the rows both operands have
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <set operator>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ06
    Given the GQL-program "RETURN 1 AS n INTERSECT RETURN 1 AS n"
    When the program is executed
    Then the result table holds one row "1"

  @iso:14.2 @feature:GQ02
  Scenario: OTHERWISE answers with its right operand only when its left found nothing
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <query conjunction>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ02
    Given the GQL-program "MATCH (p:Interface) RETURN p.name AS name OTHERWISE RETURN 'none' AS name"
    When the program is executed
    Then the result table holds one row "none"

  @iso:14.2 @feature:GQ02
  Scenario: OTHERWISE answers with its left operand when that found something
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <query conjunction>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ02
    Given the GQL-program "RETURN 'found' AS name OTHERWISE RETURN 'none' AS name"
    When the program is executed
    Then the result table holds one row "found"

  @iso:14.2
  Scenario: Two operands of different widths cannot be combined
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN 1 AS n UNION RETURN 1 AS n, 2 AS m"
    When the program is executed
    Then the GQLSTATUS is "42001"

  @iso:14.6 @feature:GQ08
  Scenario: FILTER keeps the rows its predicate is true of
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <filter statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ08
    Given the GQL-program "MATCH (a:Method) FILTER a.line > 9 RETURN count(*) AS kept"
    When the program is executed
    Then the result table holds one row "2"

  @iso:14.6 @feature:GQ08
  Scenario: FILTER takes an optional WHERE that changes nothing
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <filter statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ08
    Given the GQL-program "MATCH (a:Method) FILTER WHERE a.line > 9 RETURN count(*) AS kept"
    When the program is executed
    Then the result table holds one row "2"

  @iso:14.7 @feature:GQ09
  Scenario: LET binds a computed value to a name for the statements after it
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <let statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ09
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
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <let statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ09
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42002
    Given the GQL-program "MATCH (a:Method) LET name = a.name, greeting = name RETURN greeting AS g"
    When the program is executed
    Then the GQLSTATUS is "42002"

  @iso:14.9 @iso:16.18 @feature:GQ13
  Scenario: LIMIT keeps the first rows and no more
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.9, 16.18
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <limit clause>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ13
    Given the GQL-program "MATCH (a:Method) ORDER BY a.name LIMIT 1 RETURN a.name AS name"
    When the program is executed
    Then the result table holds one row "first"

  @iso:14.9 @iso:16.19 @feature:GQ12
  Scenario: OFFSET drops the first rows
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.9, 16.19
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <offset clause>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ12
    Given the GQL-program "MATCH (a:Method) ORDER BY a.name OFFSET 1 LIMIT 1 RETURN a.name AS name"
    When the program is executed
    Then the result table holds one row "second"

  @iso:16.19 @feature:GQ12
  Scenario: SKIP is the other way to write OFFSET
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.19
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <offset clause>, <offset synonym>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ12
    Given the GQL-program "MATCH (a:Method) ORDER BY a.name SKIP 1 LIMIT 1 RETURN a.name AS name"
    When the program is executed
    Then the result table holds one row "second"

  @iso:16.18
  Scenario: A count of rows too large to hold is out of range
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.18
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22003
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IL010, ID062
    Given the GQL-program "MATCH (a:Method) LIMIT 99999999999999999999 RETURN a.name AS name"
    When the program is read
    Then the GQLSTATUS is "22003"

  @iso:16.17 @feature:GQ14
  Scenario: A sort key may be any expression, not only a binding variable
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.17
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <sort key>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ14
    Given the GQL-program "MATCH (a:Method) RETURN a.name AS name ORDER BY char_length(a.name), a.name"
    When the program is executed
    Then the result table holds:
      """
      first
      third
      second
      """

  @iso:16.17 @iso:22.14
  Scenario: The absence of a value sorts before every value
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.17, 22.14
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IS001
    Given the GQL-program "MATCH (a) LET v = CASE WHEN a.name = 'first' THEN 1 ELSE NULL END RETURN v ORDER BY v"
    When the program is executed
    Then the result table holds:
      """
      NULL
      NULL
      NULL
      NULL
      1
      """

  @iso:16.17 @iso:22.14
  Scenario: Values of kinds the standard gives no order between cannot be sorted together
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.17, 22.14
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22G04
    Given the GQL-program "MATCH (a) LET v = CASE WHEN a.name = 'first' THEN 1 ELSE 'x' END RETURN v ORDER BY v"
    When the program is executed
    Then the GQLSTATUS is "22G04"

  @iso:14.10 @iso:22.12
  Scenario: DISTINCT shows the rows that repeat once
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.10, 22.12
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <set quantifier>
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->(b) RETURN DISTINCT b.name AS called ORDER BY called"
    When the program is executed
    Then the result table holds:
      """
      first
      second
      third
      """

  @iso:16.15 @iso:22.15 @feature:GQ15
  Scenario: GROUP BY gathers the rows that agree on what they are grouped by
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.15, 22.15
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <group by clause>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ15
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->(b) RETURN b.name AS called, count(*) AS callers GROUP BY called ORDER BY called"
    When the program is executed
    Then the result table holds:
      """
      first, 1
      second, 2
      third, 1
      """

  @iso:16.15 @feature:GQ15
  Scenario: GROUP BY names what it groups by, rather than computing it
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.15
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <grouping element>, <binding variable reference>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ15
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (a:Method) RETURN a.name AS name GROUP BY a.name"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:16.17 @feature:GQ16
  Scenario: A sort key may name a column the same projection introduces
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.17
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ16
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->(b) RETURN b.name AS called, count(*) AS callers GROUP BY called ORDER BY callers DESC, called"
    When the program is executed
    Then the result table holds:
      """
      second, 2
      first, 1
      third, 1
      """

  @iso:14.11
  Scenario: A projection may ask for everything in scope
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <return statement body>
    Given the GQL-program "LET n = 1 LET m = 2 RETURN *"
    When the program is executed
    Then the result table has columns "n:INT64, m:INT64"
