@iso:24.5
Feature: The GQL this implementation does not run
  Source: ISO/IEC 39075:2024. Clause 24.5 is Requirements for GQL-implementations,
  and an implementation that does not implement a part of the language has to say so
  rather than behave as though the part were not GQL.

  peq derives a graph by reading PHP sources and answers questions about it. It keeps
  no graph, so the statements of clauses 7, 8, 12 and 18 - sessions, transactions,
  catalog changes and data changes - are not implemented. Each is refused by name,
  under GQLSTATUS 42000, which is the class code for a query the implementation will
  not run. A syntax error would be the wrong answer: the program is GQL, correctly
  written, and this implementation is what declines it.

  The subclauses stated in this feature:
    7       Session management
    8       Transaction management
    12      Catalog-modifying statements
    13      Data-modifying statements
    14.3    <linear query statement> and <simple query statement>
    14.10   <primitive result statement>
    24.5.3  Extensions and options

  @iso:13
  Scenario Outline: A statement that would change the data is refused by name
    Given the GQL-program "<statement>"
    When the program is read
    Then the GQLSTATUS is "42000"
    And the condition is "error: syntax error or access rule violation"

    Examples:
      | statement                      |
      | INSERT (p:Person)              |
      | SET p.name = 'x'               |
      | REMOVE p.name                  |
      | DELETE (p)                     |
      | DETACH DELETE (p)              |
      | NODETACH DELETE (p)            |

  @iso:12
  Scenario Outline: A statement that would change the catalog is refused by name
    Given the GQL-program "<statement>"
    When the program is read
    Then the GQLSTATUS is "42000"

    Examples:
      | statement                 |
      | CREATE GRAPH g            |
      | DROP GRAPH g              |
      | CALL someProcedure()      |
      | USE g                     |
      | YIELD x                   |

  @iso:7
  Scenario Outline: A session command is refused by name
    Given the GQL-program "<statement>"
    When the program is read
    Then the GQLSTATUS is "42000"

    Examples:
      | statement                |
      | SESSION SET VALUE x = 1  |

  @iso:8
  Scenario Outline: A transaction command is refused by name
    Given the GQL-program "<statement>"
    When the program is read
    Then the GQLSTATUS is "42000"

    Examples:
      | statement          |
      | START TRANSACTION  |
      | COMMIT             |
      | ROLLBACK           |

  @iso:14.10
  Scenario: A statement that ends a query without a result table is refused by name
    Given the GQL-program "FINISH"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:14.3
  Scenario: The statement that chains one query onto another is refused by name
    Given the GQL-program "MATCH (a) NEXT MATCH (b) RETURN b"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:24.5
  Scenario: A word that begins no GQL statement is a syntax error, not a refusal
    Given the GQL-program "BANANA (p)"
    When the program is read
    Then the GQLSTATUS is "42001"
    And the condition is "error: syntax error or access rule violation - invalid syntax"
