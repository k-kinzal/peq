@iso:24.5
Feature: The GQL this implementation does not run
  Source: ISO/IEC 39075:2024, 24.5, Requirements for GQL-implementations. An
  implementation that does not implement a part of the language has to say so rather
  than behave as though the part were not GQL. Every scenario cites where the
  statement it declines is defined.

  peq derives a graph by reading PHP sources and answers questions about it. It keeps
  no graph, so the statements that manage sessions, transactions, the catalog and the
  data are not implemented. Each is refused by name, under GQLSTATUS 42000, the class
  code for a program the implementation will not run. A syntax error would be the
  wrong answer: the program is GQL, correctly written, and this implementation is what
  declines it.

  @iso:13
  Scenario Outline: A statement that would change the data is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 13, 13.2, 13.3, 13.4, 13.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <insert statement>, <set statement>, <remove statement>, <delete statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
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
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 12, 12.4, 12.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <create graph statement>, <drop graph statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "<statement>"
    When the program is read
    Then the GQLSTATUS is "42000"

    Examples:
      | statement                 |
      | CREATE GRAPH g            |
      | DROP GRAPH g              |

  @iso:15.1
  Scenario: A procedure call is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 15.1
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <call procedure statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "CALL someProcedure()"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:16.2
  Scenario: A clause that picks another graph is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <use graph clause>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "USE g"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:16.14
  Scenario: A clause that yields a binding table is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.14
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <yield clause>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "YIELD x"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:7.1
  Scenario: A session command is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 7.1
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <session set command>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "SESSION SET VALUE x = 1"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:8
  Scenario Outline: A transaction command is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 8, 8.1, 8.3, 8.4
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <start transaction command>, <rollback command>, <commit command>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
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
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 14.10
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <primitive result statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "FINISH"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:9.2
  Scenario: The statement that chains one query onto another is refused by name
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 9.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <next statement>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml GQ20
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42000
    Given the GQL-program "MATCH (a) NEXT MATCH (b) RETURN b"
    When the program is read
    Then the GQLSTATUS is "42000"

  @iso:24.5
  Scenario: A word that begins no GQL statement is a syntax error, not a refusal
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "BANANA (p)"
    When the program is read
    Then the GQLSTATUS is "42001"
    And the condition is "error: syntax error or access rule violation - invalid syntax"
