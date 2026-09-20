@iso:16
Feature: Graph pattern matching
  Source: ISO/IEC 39075:2024, clause 16, Graph pattern matching. Each scenario is
  tagged with the subclause it states and, where it states an optional feature, with
  the feature code from spec/iso/features.xml.

  Every scenario runs against the graph in spec/Context/SpecificationGraph.php:
  three methods of Spec\Ring that call round in a ring, with the third calling back
  into the second as well, and two classes. The shape is chosen so that the four
  path modes of 16.8 disagree about it, which is the hardest thing this feature has
  to state.

  The subclauses stated in this feature:
    16.4    <graph pattern>
    16.6    <path pattern prefix>
    16.7    <path pattern expression>
    16.8    <label expression>
    16.11   <graph pattern quantifier>

  @iso:16.7 @feature:G043
  Scenario: An edge pattern written in full states what it requires of the edge
    Given the GQL-program "MATCH (a:Method)-[e:methodCall]->(b:Method) RETURN a.name AS caller, b.name AS called ORDER BY caller, called"
    When the program is executed
    Then the result table has columns "caller:STRING, called:STRING"
    And the result table holds:
      """
      first, second
      second, third
      third, first
      third, second
      """

  @iso:16.7 @feature:G044
  Scenario: An edge pattern may be abbreviated to an arrow that requires nothing
    Given the GQL-program "MATCH (a:Method)->(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "4"

  @iso:16.7 @feature:G045
  Scenario: The abbreviated form states its direction the way the full form does
    Given the GQL-program "MATCH (a:Method)<-(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "4"

  @iso:16.7 @feature:GH02
  Scenario: An undirected edge pattern crosses an edge whichever way it points
    Given the GQL-program "MATCH (a:Method)-[:methodCall]-(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "8"

  @iso:16.8 @feature:G074
  Scenario: The wildcard label is satisfied by an element that carries any label
    Given the GQL-program "MATCH (a:%) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "5"

  @iso:16.8
  Scenario: A label expression may require either of two labels
    Given the GQL-program "MATCH (a:Method|Class) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "5"

  @iso:16.8
  Scenario: A label expression may require both of two labels
    Given the GQL-program "MATCH (a:Member&Callable) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "3"

  @iso:16.8
  Scenario: A label expression may refuse a label
    Given the GQL-program "MATCH (a:ClassLike&!Interface) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "2"

  @iso:16.11 @feature:G036 @feature:G060
  Scenario: A quantified edge pattern crosses a bounded number of edges
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[:methodCall]->{2}(b:Method) RETURN b.name AS reached ORDER BY reached"
    When the program is executed
    Then the result table holds one row "third"

  @iso:16.11 @feature:G061
  Scenario: A quantifier may be written without an upper bound
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[:methodCall]->{1,}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the GQLSTATUS is "00000"

  @iso:16.7 @feature:G038 @feature:G035
  Scenario: A parenthesized path pattern may be quantified as a whole
    Given the GQL-program "MATCH ((a:Method)-[:methodCall]->(b:Method)){1,2} RETURN count(*) AS matched"
    When the program is executed
    Then the GQLSTATUS is "00000"

  @iso:16.4 @feature:G004
  Scenario: A path pattern may be given a path variable
    Given the GQL-program "MATCH p = (a:Method WHERE a.name = 'first')-[:methodCall]->(b) RETURN path_length(p) AS hops"
    When the program is executed
    Then the result table has columns "hops:INT64"
    And the result table holds one row "1"

  @iso:16.6 @feature:G010
  Scenario: WALK places no restriction on what a path repeats
    Given the GQL-program "MATCH WALK (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "53"

  @iso:16.6 @feature:G011
  Scenario: TRAIL forbids a path from crossing the same edge twice
    Given the GQL-program "MATCH TRAIL (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "16"

  @iso:16.6 @feature:G012
  Scenario: SIMPLE forbids a repeated node, except that the ends may be the same
    Given the GQL-program "MATCH SIMPLE (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "13"

  @iso:16.6 @feature:G012
  Scenario: SIMPLE allows the path that comes back to where it started
    Given the GQL-program "MATCH SIMPLE (a:Method WHERE a.name = 'first')-[:methodCall]->{3}(b:Method WHERE b.name = 'first') RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "1"

  @iso:16.6 @feature:G013
  Scenario: ACYCLIC forbids a repeated node at all
    Given the GQL-program "MATCH ACYCLIC (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "7"

  @iso:16.6 @feature:G013
  Scenario: ACYCLIC forbids the path that comes back to where it started
    Given the GQL-program "MATCH ACYCLIC (a:Method WHERE a.name = 'first')-[:methodCall]->{3}(b:Method WHERE b.name = 'first') RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "0"

  @iso:16.6
  Scenario: A path pattern that says nothing crosses no edge twice
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "16"

  @iso:16.7
  Scenario: A variable written twice in one pattern requires the same element twice
    Given the GQL-program "MATCH (c:Class)-[:declaresMethod]->(a), (c)-[:declaresMethod]->(b) WHERE a.name <> b.name RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "6"

  @iso:16.7
  Scenario: An element pattern may require properties of the element it matches
    Given the GQL-program "MATCH (a:Method {name: 'first'}) RETURN a.name AS matched"
    When the program is executed
    Then the result table holds one row "first"

  @iso:16.7
  Scenario: An element pattern may carry a predicate about the element it matches
    Given the GQL-program "MATCH (a:Method WHERE a.line > 9) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "2"
