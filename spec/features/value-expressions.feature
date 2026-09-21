@iso:20
Feature: Value expressions
  Source: ISO/IEC 39075:2024, clause 20, Value expressions and specifications, with
  clause 19, Predicates, and clause 22 for the rules about comparing and combining
  values. Every scenario cites where what it states is published: a subclause of the
  standard, a production of its grammar, and a code in its artifacts.

  @iso:19.3 @iso:22.13
  Scenario: Two values that are equal compare true
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3, 22.13
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <comparison predicate>
    Given the GQL-program "RETURN 5 = 5 AS answer"
    When the program is executed
    Then the result table has columns "answer:BOOL"
    And the result table holds one row "TRUE"

  @iso:19.3 @iso:22.13
  Scenario: Two values that are not equal compare false
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3, 22.13
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <comparison predicate>
    Given the GQL-program "RETURN 5 = 3 AS answer"
    When the program is executed
    Then the result table holds one row "FALSE"

  @iso:19.3 @iso:22.13
  Scenario: A comparison against the null value is unknown, not false
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3, 22.13
    Given the GQL-program "RETURN 5 = NULL AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:19.3 @iso:22.13
  Scenario: The null value is not even equal to itself
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3, 22.13
    Given the GQL-program "RETURN NULL = NULL AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:19.3 @iso:22.13
  Scenario: Numbers of different kinds compare as numbers
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3, 22.13
    Given the GQL-program "RETURN 2 = 2.0 AS against_decimal, 2 = 2e0 AS against_float"
    When the program is executed
    Then the result table holds one row "TRUE, TRUE"

  @iso:19.3 @iso:22.13
  Scenario: Values of kinds the standard gives no order between cannot be compared
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3, 22.13
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22G04
    Given the GQL-program "RETURN 1 = 'a' AS answer"
    When the program is executed
    Then the GQLSTATUS is "22G04"
    And the condition is "error: data exception - values not comparable"

  @iso:19.3
  Scenario: A comparison has two operands, and one comparison is not the operand of another
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <comparison predicate>, <comparison predicate part 2>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN 1 < 2 < 3 AS answer"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:22.14
  Scenario: Character strings are ordered by the code points of their characters
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 22.14
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID022
    Given the GQL-program "RETURN 'B' < 'a' AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:22.13 @feature:GA09
  Scenario: Two paths are equal when they are made of the same elements in the same order
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 22.13
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GA09
    Given the GQL-program "MATCH p = (a:Method WHERE a.name = 'first')-[:methodCall]->(b), q = (c:Method WHERE c.name = 'first')-[:methodCall]->(d) RETURN p = q AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.20
  Scenario: The negation of unknown is unknown
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean factor>
    Given the GQL-program "RETURN NOT (5 = NULL) AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.20
  Scenario: A negation is written once before what it negates
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean factor>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN NOT NOT TRUE AS answer"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:20.20
  Scenario: A conjunction with one false operand is false whatever the other is
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean term>
    Given the GQL-program "RETURN FALSE AND NULL AS answer"
    When the program is executed
    Then the result table holds one row "FALSE"

  @iso:20.20
  Scenario: A disjunction with one true operand is true whatever the other is
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean value expression>
    Given the GQL-program "RETURN TRUE OR NULL AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.20 @feature:GE07
  Scenario: Exclusive disjunction is true when exactly one operand is true
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean value expression>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GE07
    Given the GQL-program "RETURN TRUE XOR FALSE AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.20 @feature:GE07
  Scenario: Exclusive disjunction is unknown while either operand is
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean value expression>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GE07
    Given the GQL-program "RETURN TRUE XOR NULL AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.20 @feature:GE07
  Scenario: Disjunction and exclusive disjunction bind equally, left to right
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean value expression>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GE07
    Given the GQL-program "RETURN TRUE OR TRUE XOR TRUE AS answer"
    When the program is executed
    Then the result table holds one row "FALSE"

  @iso:20.20
  Scenario: A truth test asks whether a condition is true, and always has an answer
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean test>, <truth value>
    Given the GQL-program "RETURN (5 = NULL) IS TRUE AS holds, (5 = NULL) IS NOT TRUE AS fails"
    When the program is executed
    Then the result table holds one row "FALSE, TRUE"

  @iso:20.20
  Scenario: A truth test may ask whether a condition is unknown
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean test>, <truth value>
    Given the GQL-program "RETURN (5 = NULL) IS UNKNOWN AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:19.5
  Scenario: A null predicate always has an answer
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <null predicate>
    Given the GQL-program "RETURN NULL IS NULL AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:19.5
  Scenario: A value that is there is not null
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <null predicate>
    Given the GQL-program "RETURN 1 IS NOT NULL AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.21
  Scenario: Two whole numbers divide into a whole number
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID064, ID067
    Given the GQL-program "RETURN 7 / 2 AS n"
    When the program is executed
    Then the result table has columns "n:INT64"
    And the result table holds one row "3"

  @iso:20.21
  Scenario: Exact numbers with digits after the point divide exactly, to six places
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID064, ID067, IA011
    Given the GQL-program "RETURN 7.0 / 2 AS half, 1.0 / 3 AS third"
    When the program is executed
    Then the result table has columns "half:DECIMAL, third:DECIMAL"
    And the result table holds one row "3.500000, 0.333333"

  @iso:20.21
  Scenario: Exact numbers add exactly
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID064, ID065
    Given the GQL-program "RETURN 0.1 + 0.2 AS n"
    When the program is executed
    Then the result table has columns "n:DECIMAL"
    And the result table holds one row "0.3"

  @iso:20.21
  Scenario: The scale of a product is the sum of the scales of its factors
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID066
    Given the GQL-program "RETURN 1.5 * 0.2 AS n"
    When the program is executed
    Then the result table holds one row "0.30"

  @iso:20.21
  Scenario: An expression that meets an approximate number produces an approximate one
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID063, ID037
    Given the GQL-program "RETURN 1 + 1.5e0 AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "2.5"

  @iso:20.21
  Scenario: An expression that meets the null value produces the null value
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Given the GQL-program "RETURN 1 + NULL AS n"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.21
  Scenario: A division by zero is a data exception, not an answer
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22012
    Given the GQL-program "RETURN 1 / 0 AS n"
    When the program is executed
    Then the GQLSTATUS is "22012"
    And the condition is "error: data exception - division by zero"

  @iso:20.21
  Scenario: An exact result too large to hold is out of range, not approximated
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22003
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID028, IL011
    Given the GQL-program "RETURN 9223372036854775807 + 1 AS n"
    When the program is executed
    Then the GQLSTATUS is "22003"
    And the condition is "error: data exception - numeric value out of range"

  @iso:20.23
  Scenario: Two character strings are joined by the concatenation operator
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.23
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <character string concatenation>
    Given the GQL-program "RETURN 'a' || 'b' AS joined"
    When the program is executed
    Then the result table holds one row "ab"

  @iso:20.23
  Scenario: Joining to something that is not there has no answer
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.23
    Given the GQL-program "RETURN 'a' || NULL AS joined"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.23
  Scenario: Only strings are joined to strings
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.23
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22G03
    Given the GQL-program "RETURN 'line ' || 12 AS joined"
    When the program is executed
    Then the GQLSTATUS is "22G03"
    And the condition is "error: data exception - invalid value type"

  @iso:20.15 @feature:GV50
  Scenario: Two lists are joined by the concatenation operator
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.15
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <list concatenation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GV50
    Given the GQL-program "RETURN [1] || [2, 3] AS joined"
    When the program is executed
    Then the result table holds one row "[1, 2, 3]"

  @iso:20.24
  Scenario: A prefix is asked for with the substring function read from the left
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.24
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <substring function>
    Given the GQL-program "MATCH (m:Method) FILTER left(m.id, 9) = 'Spec\\Ring' RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "3"

  @iso:20.24
  Scenario: A suffix is asked for with the substring function read from the right
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.24
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <substring function>
    Given the GQL-program "MATCH (c:Class) FILTER right(c.name, 4) = 'Ring' RETURN c.name AS n"
    When the program is executed
    Then the result table holds one row "Ring"

  @iso:20.24
  Scenario: A substring longer than the string is the whole string
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.24
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <substring function>
    Given the GQL-program "RETURN left('ab', 9) AS taken"
    When the program is executed
    Then the result table holds one row "ab"

  @iso:20.24
  Scenario: A substring of a negative length is a substring error
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.24
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22011
    Given the GQL-program "RETURN left('ab', -1) AS taken"
    When the program is executed
    Then the GQLSTATUS is "22011"
    And the condition is "error: data exception - substring error"

  @iso:19.2
  Scenario Outline: A predicate another graph language spells is not one GQL spells
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 19.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <predicate>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN 'ab' <operator> 'a' AS answer"
    When the program is read
    Then the GQLSTATUS is "42001"

    Examples:
      | operator    |
      | CONTAINS    |
      | STARTS WITH |
      | ENDS WITH   |
      | IN          |

  @iso:20.20
  Scenario: Negation binds tighter than disjunction
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <boolean value expression>, <boolean factor>
    Given the GQL-program "RETURN NOT TRUE OR TRUE AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.21
  Scenario: Multiplication binds tighter than addition
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <numeric value expression>, <term>
    Given the GQL-program "RETURN 1 + 2 * 3 AS n"
    When the program is executed
    Then the result table holds one row "7"

  @iso:20.7
  Scenario: A searched case takes the first branch whose condition holds
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <searched case>
    Given the GQL-program "RETURN CASE WHEN FALSE THEN 'first' WHEN TRUE THEN 'second' ELSE 'none' END AS taken"
    When the program is executed
    Then the result table holds one row "second"

  @iso:20.7
  Scenario: A simple case compares its operand with each branch
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <simple case>
    Given the GQL-program "RETURN CASE 2 WHEN 1 THEN 'one' WHEN 2 THEN 'two' ELSE 'other' END AS taken"
    When the program is executed
    Then the result table holds one row "two"

  @iso:20.7
  Scenario: A case that matches nothing and offers no else is the null value
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <searched case>
    Given the GQL-program "RETURN CASE WHEN FALSE THEN 'x' END AS taken"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.9
  Scenario: An aggregate passes over the null values it is offered
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <general set function>
    Given the GQL-program "MATCH (a) RETURN count(a.visibility) AS named"
    When the program is executed
    Then the result table holds one row "0"

  @iso:20.9
  Scenario: A count over rows counts them whatever they carry
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <aggregate function>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID059
    Given the GQL-program "MATCH (a) RETURN count(*) AS rows"
    When the program is executed
    Then the result table has columns "rows:INT64"
    And the result table holds one row "5"

  @iso:20.9
  Scenario: An asterisk is counted over, and nothing else is written with one
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <aggregate function>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (a) RETURN sum(*) AS rows"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:20.9
  Scenario: Only an aggregate is written with a set quantifier
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <general set function>, <set quantifier>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "RETURN upper(DISTINCT 'a') AS shouted"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:20.9
  Scenario: An aggregate offered nothing material answers with the null value
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Given the GQL-program "MATCH (a:Interface) RETURN sum(a.line) AS total"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.9
  Scenario: Counting nothing is zero, which is the exception the standard makes
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Given the GQL-program "MATCH (a:Interface) RETURN count(*) AS rows"
    When the program is executed
    Then the result table holds one row "0"

  @iso:20.9
  Scenario: The sum of exact numbers is exact
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID095
    Given the GQL-program "MATCH (a:Method) RETURN sum(a.line) AS whole, sum(a.line * 0.5) AS halves"
    When the program is executed
    Then the result table has columns "whole:INT64, halves:DECIMAL"
    And the result table holds one row "30, 15.0"

  @iso:20.9
  Scenario: The average of exact numbers is exact, to six places
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID096
    Given the GQL-program "MATCH (a:Method) RETURN avg(a.line) AS mean"
    When the program is executed
    Then the result table has columns "mean:DECIMAL"
    And the result table holds one row "10.000000"

  @iso:20.9
  Scenario: The sum and average of approximate numbers are approximate
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID097
    Given the GQL-program "MATCH (a:Method) RETURN sum(a.line * 1e0) AS total, avg(a.line * 1e0) AS mean"
    When the program is executed
    Then the result table has columns "total:FLOAT64, mean:FLOAT64"
    And the result table holds one row "30.0, 10.0"

  @iso:20.9 @iso:22.7 @feature:GE09
  Scenario: A summary over a group list summarises the row rather than the rows
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9, 22.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GE09
    Source: https://arxiv.org/abs/2112.06217 §4.4
    Given the GQL-program "MATCH (a:Method)-[e:methodCall]->{2}(b:Method) RETURN a.name AS caller, min(e.line) AS earliest ORDER BY caller"
    When the program is executed
    Then the result table holds:
      """
      first, 6
      second, 11
      second, 11
      third, 6
      third, 11
      """

  @iso:20.9 @iso:22.7 @feature:GE09
  Scenario: A horizontal summary may itself be summarised down the rows
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.9, 22.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GE09
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID096
    Given the GQL-program "MATCH (a:Method)-[e:methodCall]->{2}(b:Method) RETURN count(*) AS found, avg(min(e.line)) AS mean"
    When the program is executed
    Then the result table has columns "found:INT64, mean:DECIMAL"

  @iso:20.22 @feature:GF13
  Scenario: SIZE answers how many values a list holds
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.22
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GF13
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <cardinality expression>
    Given the GQL-program "RETURN size([1, 2, 3]) AS n"
    When the program is executed
    Then the result table holds one row "3"

  @iso:20.22 @iso:22.7 @feature:GF13
  Scenario: SIZE answers how many edges a group list holds
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.22, 22.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GF13
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[e:methodCall]->{2}(b) RETURN size(e) AS hops"
    When the program is executed
    Then the result table holds one row "2"

  @iso:20.12
  Scenario: A name nothing bound is an invalid reference, not the null value
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 20.12
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <binding variable reference>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42002
    Given the GQL-program "RETURN nothingBindsThis AS n"
    When the program is executed
    Then the GQLSTATUS is "42002"
