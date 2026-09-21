@iso:16
Feature: Graph pattern matching
  Source: ISO/IEC 39075:2024, clause 16, Graph pattern matching, and clause 22, the
  evaluation of what it defines. Every scenario cites where what it states is
  published: a subclause of the standard, a production of its grammar, a code in its
  artifacts, and where it helps, a section of "Graph Pattern Matching in GQL and
  SQL/PGQ", the paper in which the editors of the standard explain this clause.

  Every scenario runs against the graph in spec/Context/SpecificationGraph.php:
  three methods of Spec\Ring that call round in a ring, with the third calling back
  into the second as well, and two classes. The shape is chosen so that the four
  path modes of 16.6 disagree about it, which is the hardest thing this feature has
  to state.

  @iso:16.7
  Scenario: An edge pattern written in full states what it requires of the edge
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7, 22.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <full edge pointing right>
    Source: https://arxiv.org/abs/2112.06217 §4.1
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

  @iso:16.7
  Scenario: An edge pattern pointing left crosses an edge from where it points to where it starts
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <full edge pointing left>
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')<-[:methodCall]-(b:Method) RETURN b.name AS caller"
    When the program is executed
    Then the result table holds one row "third"

  @iso:16.7
  Scenario: An edge pattern without direction crosses an edge whichever way it points
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <full edge any direction>
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method)-[:methodCall]-(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "8"

  @iso:16.7 @feature:G043
  Scenario: An edge pattern pointing both ways crosses a directed edge whichever way it points
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <full edge left or right>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G043
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method)<-[:methodCall]->(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "8"

  @iso:16.7 @feature:G043 @feature:GH02
  Scenario: An undirected edge pattern crosses only undirected edges, and this graph has none
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <full edge undirected>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G043, GH02
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method)~[:methodCall]~(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "0"

  @iso:16.7 @feature:G043 @feature:GH02
  Scenario: An edge pattern that allows an undirected edge or a directed one crosses the directed ones
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <full edge left or undirected>, <full edge undirected or right>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G043, GH02
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method)<~[:methodCall]~(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "4"

  @iso:16.7 @feature:G044
  Scenario: An edge pattern may be abbreviated to an arrow that requires nothing
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <abbreviated edge pattern>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G044
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method)->(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "4"

  @iso:16.7 @feature:G044
  Scenario: The abbreviated form states its direction the way the full form does
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <abbreviated edge pattern>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G044
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')<-(b:Method) RETURN b.name AS caller"
    When the program is executed
    Then the result table holds one row "third"

  @iso:16.7 @feature:G045
  Scenario: The abbreviations of the other five edge patterns are written as well
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <abbreviated edge pattern>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G045, GH02
    Source: https://arxiv.org/abs/2112.06217 §4.1
    Given the GQL-program "MATCH (a:Method)<->(b:Method), (c:Method)~>(d:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "32"

  @iso:16.8 @feature:G074
  Scenario: The wildcard label is satisfied by an element that carries any label
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.8, 22.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <wildcard label>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G074
    Given the GQL-program "MATCH (a:%) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "5"

  @iso:16.8
  Scenario: A label expression may require either of two labels
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.8, 22.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <label disjunction>
    Given the GQL-program "MATCH (a:Method|Class) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "5"

  @iso:16.8
  Scenario: A label expression may require both of two labels
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.8, 22.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <label conjunction>
    Given the GQL-program "MATCH (a:Member&Callable) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "3"

  @iso:16.8
  Scenario: A label expression may refuse a label
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.8, 22.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <label negation>
    Given the GQL-program "MATCH (a:ClassLike&!Interface) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "2"

  @iso:16.8
  Scenario: A negation is of a label, a wildcard or a parenthesised expression, not of another negation
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.8
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <label negation>, <label primary>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (a:!!Method) RETURN count(*) AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:16.7
  Scenario: A label expression may be introduced by IS as well as by a colon
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <is or colon>
    Given the GQL-program "MATCH (a IS Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "3"

  @iso:16.11 @feature:G036 @feature:G060
  Scenario: A quantified edge pattern crosses a bounded number of edges
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <quantified path primary>, <fixed quantifier>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G036, G060
    Source: https://arxiv.org/abs/2112.06217 §4.4
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[:methodCall]->{2}(b:Method) RETURN b.name AS reached ORDER BY reached"
    When the program is executed
    Then the result table holds one row "third"

  @iso:16.6 @iso:16.11 @feature:G061 @feature:G011
  Scenario: A quantifier may be written without an upper bound under a restrictor
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6, 16.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <general quantifier>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G061, G011
    Source: https://arxiv.org/abs/2112.06217 §5, §5.1
    Given the GQL-program "MATCH TRAIL (a:Method WHERE a.name = 'first')-[:methodCall]->{1,}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "4"

  @iso:16.6 @iso:16.11
  Scenario: A quantifier without an upper bound is refused where no restrictor keeps its matches finite
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6, 16.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <general quantifier>, <path mode prefix>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Source: https://arxiv.org/abs/2112.06217 §5
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[:methodCall]->{1,}(b:Method) RETURN count(*) AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"

  @iso:16.7 @feature:G038 @feature:G035
  Scenario: A parenthesized path pattern may be quantified as a whole
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7, 16.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <parenthesized path pattern expression>, <quantified path primary>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G038, G035
    Source: https://arxiv.org/abs/2112.06217 §4.4
    Given the GQL-program "MATCH ((a:Method)-[:methodCall]->(b:Method)){2} RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "5"

  @iso:16.7 @iso:22.7 @feature:G035
  Scenario: A variable declared inside a quantified pattern is bound to what every repetition bound
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7, 22.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <quantified path primary>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G035
    Source: https://arxiv.org/abs/2112.06217 §4.4
    Given the GQL-program "MATCH (s:Method WHERE s.name = 'first')((a)-[:methodCall]->(b)){2} RETURN size(a) AS callers, size(b) AS called"
    When the program is executed
    Then the result table holds one row "2, 2"

  @iso:16.7 @feature:G046
  Scenario: Two node patterns written side by side are the same node
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path concatenation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G046
    Source: https://arxiv.org/abs/2112.06217 §6.2
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')(b) RETURN b.name AS matched"
    When the program is executed
    Then the result table holds one row "first"

  @iso:16.7 @feature:G047
  Scenario: Two edge patterns written side by side have a node between them
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path concatenation>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G047
    Source: https://arxiv.org/abs/2112.06217 §6.2
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[:methodCall]->-[:methodCall]->(c) RETURN c.name AS reached"
    When the program is executed
    Then the result table holds one row "third"

  @iso:16.4 @feature:G004
  Scenario: A path pattern may be given a path variable
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.4
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path variable declaration>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G004
    Given the GQL-program "MATCH p = (a:Method WHERE a.name = 'first')-[:methodCall]->(b) RETURN path_length(p) AS hops"
    When the program is executed
    Then the result table has columns "hops:INT64"
    And the result table holds one row "1"

  @iso:16.6 @feature:G010
  Scenario: WALK places no restriction on what a path repeats
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G010
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH WALK (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "53"

  @iso:16.6
  Scenario: A path pattern that names no path mode is a walk
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode prefix>
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "53"

  @iso:16.6 @feature:G011
  Scenario: TRAIL forbids a path from crossing the same edge twice
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G011
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH TRAIL (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "16"

  @iso:16.6 @feature:G012
  Scenario: SIMPLE forbids a repeated node, except that the ends may be the same
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G012
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH SIMPLE (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "13"

  @iso:16.6 @feature:G012
  Scenario: SIMPLE allows the path that comes back to where it started
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G012
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH SIMPLE (a:Method WHERE a.name = 'first')-[:methodCall]->{3}(b:Method WHERE b.name = 'first') RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "1"

  @iso:16.6 @feature:G013
  Scenario: ACYCLIC forbids a repeated node at all
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G013
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH ACYCLIC (a:Method)-[:methodCall]->{1,6}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "7"

  @iso:16.6 @feature:G013
  Scenario: ACYCLIC forbids the path that comes back to where it started
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.6
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <path mode>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G013
    Source: https://arxiv.org/abs/2112.06217 §5.1
    Given the GQL-program "MATCH ACYCLIC (a:Method WHERE a.name = 'first')-[:methodCall]->{3}(b:Method WHERE b.name = 'first') RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "0"

  @iso:16.4
  Scenario: A variable written twice in one pattern requires the same element twice
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.4
    Source: https://arxiv.org/abs/2112.06217 §4.3
    Given the GQL-program "MATCH (c:Class)-[:declaresMethod]->(a), (c)-[:declaresMethod]->(b) WHERE a.name <> b.name RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "6"

  @iso:16.7
  Scenario: An element pattern may require properties of the element it matches
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <element property specification>
    Given the GQL-program "MATCH (a:Method {name: 'first'}) RETURN a.name AS matched"
    When the program is executed
    Then the result table holds one row "first"

  @iso:16.7
  Scenario: An element pattern may carry a predicate about the element it matches
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <element pattern where clause>
    Given the GQL-program "MATCH (a:Method WHERE a.line > 9) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "2"

  @iso:16.7
  Scenario: An element pattern carries properties or a predicate, not both
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.7
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <element pattern predicate>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (a:Method {name: 'first'} WHERE a.line > 1) RETURN a.name AS matched"
    When the program is read
    Then the GQLSTATUS is "42001"
