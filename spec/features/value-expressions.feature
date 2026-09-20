@iso:20
Feature: Value expressions
  Source: ISO/IEC 39075:2024, clause 20, Value expressions and specifications, with
  clause 22, Additional common rules, for the rules about comparing and combining
  values. Each scenario is tagged with the subclause it states and, where it states
  an optional feature, with the feature code from spec/iso/features.xml.

  The subclauses stated in this feature:
    20.1    <value expression>
    20.2    <value expression primary>
    20.7    <case expression>
    20.9    <aggregate function>
    20.20   <boolean value expression>
    20.21   <numeric value expression>
    20.23   <string value expression>
    20.24   <character string function>
    22.13   Equality operations
    22.14   Ordering operations
    22.15   Grouping operations

  @iso:22.13
  Scenario: Two values that are equal compare true
    Given the GQL-program "RETURN 5 = 5 AS answer"
    When the program is executed
    Then the result table has columns "answer:BOOL"
    And the result table holds one row "TRUE"

  @iso:22.13
  Scenario: Two values that are not equal compare false
    Given the GQL-program "RETURN 5 = 3 AS answer"
    When the program is executed
    Then the result table holds one row "FALSE"

  @iso:22.13
  Scenario: A comparison against the null value is unknown, not false
    Given the GQL-program "RETURN 5 = NULL AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:22.13
  Scenario: The null value is not even equal to itself
    Given the GQL-program "RETURN NULL = NULL AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.20
  Scenario: The negation of unknown is unknown
    Given the GQL-program "RETURN NOT (5 = NULL) AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.20
  Scenario: A conjunction with one false operand is false whatever the other is
    Given the GQL-program "RETURN FALSE AND NULL AS answer"
    When the program is executed
    Then the result table holds one row "FALSE"

  @iso:20.20
  Scenario: A disjunction with one true operand is true whatever the other is
    Given the GQL-program "RETURN TRUE OR NULL AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.20 @feature:GE07
  Scenario: Exclusive disjunction is true when exactly one operand is true
    Given the GQL-program "RETURN TRUE XOR FALSE AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.20 @feature:GE07
  Scenario: Exclusive disjunction is unknown while either operand is
    Given the GQL-program "RETURN TRUE XOR NULL AS answer"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.20
  Scenario: A null predicate always has an answer
    Given the GQL-program "RETURN NULL IS NULL AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.20
  Scenario: A value that is there is not null
    Given the GQL-program "RETURN 1 IS NOT NULL AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.21
  Scenario: Two exact numbers divide into an exact number
    Given the GQL-program "RETURN 7 / 2 AS n"
    When the program is executed
    Then the result table has columns "n:INT64"
    And the result table holds one row "3"

  @iso:20.21
  Scenario: An expression that meets an approximate number produces an approximate one
    Given the GQL-program "RETURN 7 / 2.0 AS n"
    When the program is executed
    Then the result table has columns "n:FLOAT64"
    And the result table holds one row "3.5"

  @iso:20.21
  Scenario: An expression that meets the null value produces the null value
    Given the GQL-program "RETURN 1 + NULL AS n"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.21
  Scenario: A division by zero is a data exception, not an answer
    Given the GQL-program "RETURN 1 / 0 AS n"
    When the program is executed
    Then the GQLSTATUS is "22012"
    And the condition is "error: data exception - division by zero"

  @iso:20.23
  Scenario: Two character strings are joined by the concatenation operator
    Given the GQL-program "RETURN 'a' || 'b' AS joined"
    When the program is executed
    Then the result table holds one row "ab"

  @iso:20.23
  Scenario: Joining to something that is not there has no answer
    Given the GQL-program "RETURN 'a' || NULL AS joined"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.24
  Scenario: A prefix is asked for with the substring function read from the left
    Given the GQL-program "MATCH (m:Method) FILTER left(m.id, 9) = 'Spec\\Ring' RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "3"

  @iso:20.24
  Scenario: A suffix is asked for with the substring function read from the right
    Given the GQL-program "MATCH (c:Class) FILTER right(c.name, 4) = 'Ring' RETURN c.name AS n"
    When the program is executed
    Then the result table holds one row "Ring"

  @iso:20.24
  Scenario: A substring longer than the string is the whole string
    Given the GQL-program "RETURN left('ab', 9) AS taken"
    When the program is executed
    Then the result table holds one row "ab"

  @iso:20.24
  Scenario: A substring of a negative length is a substring error
    Given the GQL-program "RETURN left('ab', -1) AS taken"
    When the program is executed
    Then the GQLSTATUS is "22011"
    And the condition is "error: data exception - substring error"

  @iso:20.23
  Scenario Outline: A predicate another graph language spells is not one GQL spells
    Given the GQL-program "RETURN 'ab' <operator> 'a' AS answer"
    When the program is read
    Then the GQLSTATUS is "42001"

    Examples:
      | operator    |
      | CONTAINS    |
      | STARTS WITH |
      | ENDS WITH   |

  @iso:20.1
  Scenario: Operators bind in the order the standard gives them
    Given the GQL-program "RETURN NOT TRUE OR TRUE AS answer"
    When the program is executed
    Then the result table holds one row "TRUE"

  @iso:20.1
  Scenario: Multiplication binds tighter than addition
    Given the GQL-program "RETURN 1 + 2 * 3 AS n"
    When the program is executed
    Then the result table holds one row "7"

  @iso:20.7
  Scenario: A searched case takes the first branch whose condition holds
    Given the GQL-program "RETURN CASE WHEN FALSE THEN 'first' WHEN TRUE THEN 'second' ELSE 'none' END AS taken"
    When the program is executed
    Then the result table holds one row "second"

  @iso:20.7
  Scenario: A simple case compares its operand with each branch
    Given the GQL-program "RETURN CASE 2 WHEN 1 THEN 'one' WHEN 2 THEN 'two' ELSE 'other' END AS taken"
    When the program is executed
    Then the result table holds one row "two"

  @iso:20.7
  Scenario: A case that matches nothing and offers no else is the null value
    Given the GQL-program "RETURN CASE WHEN FALSE THEN 'x' END AS taken"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.9
  Scenario: An aggregate passes over the null values it is offered
    Given the GQL-program "MATCH (a) RETURN count(a.visibility) AS named"
    When the program is executed
    Then the result table holds one row "0"

  @iso:20.9
  Scenario: A count over rows counts them whatever they carry
    Given the GQL-program "MATCH (a) RETURN count(*) AS rows"
    When the program is executed
    Then the result table holds one row "5"

  @iso:20.9
  Scenario: An aggregate offered nothing material answers with the null value
    Given the GQL-program "MATCH (a:Interface) RETURN sum(a.line) AS total"
    When the program is executed
    Then the result table holds one row "NULL"

  @iso:20.9
  Scenario: Counting nothing is zero, which is the exception the standard makes
    Given the GQL-program "MATCH (a:Interface) RETURN count(*) AS rows"
    When the program is executed
    Then the result table holds one row "0"

  @iso:20.9 @feature:GE09
  Scenario: A summary over a group list summarises the row rather than the rows
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

  @iso:20.9 @feature:GE09
  Scenario: A horizontal summary may itself be summarised down the rows
    Given the GQL-program "MATCH (a:Method)-[e:methodCall]->{2}(b:Method) RETURN count(*) AS found, avg(min(e.line)) AS mean"
    When the program is executed
    Then the result table has columns "found:INT64, mean:FLOAT64"

  @iso:20.9 @feature:GF13
  Scenario: SIZE answers how many values a list holds
    Given the GQL-program "RETURN size([1, 2, 3]) AS n"
    When the program is executed
    Then the result table holds one row "3"

  @iso:20.9 @feature:GF13
  Scenario: SIZE answers how many edges a group list holds
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[e:methodCall]->{2}(b) RETURN size(e) AS hops"
    When the program is executed
    Then the result table holds one row "2"

  @iso:20.2
  Scenario: A name nothing bound is an invalid reference, not the null value
    Given the GQL-program "RETURN nothingBindsThis AS n"
    When the program is executed
    Then the GQLSTATUS is "42002"
