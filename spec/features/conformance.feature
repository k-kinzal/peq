@iso:24
Feature: Conformance to ISO/IEC 39075
  Source: ISO/IEC 39075:2024, Information technology - Database languages - GQL,
  edition 1 (https://www.iso.org/standard/76120.html). Every scenario of this suite
  is tagged with the subclause it states, as "iso:" followed by the subclause number
  from spec/iso/subclauses.txt, and a scenario stating an optional feature carries
  its code as "feature:" followed by the code from spec/iso/features.xml.

  ISO publishes no executable test suite for GQL and recognises none, so there is
  nothing to run and claim a pass from. What clause 24 asks for instead is a claim:
  24.2 the class of conformance, 24.3 the optional features implemented, and 24.5.3
  any extension. This feature is that claim, and the five steps under it are what
  make it checkable rather than asserted - three look every line up in the artifact
  ISO publishes, and two tie the register to the rest of this suite in both
  directions, so that a feature is claimed exactly when a scenario states it. A
  register nothing exercises would be a longer way of saying "it conforms".

  Every scenario of this suite cites where what it states is published, and the
  citations are looked up too: a scenario nobody can trace to a published text is a
  rule somebody made up, and this suite has none.

  peq claims no extension under 24.5.3. Every GQL-program it reads is one the
  standard defines, and where its own vocabulary spells a word GQL reserves - a
  label called "Function", a property called "value" - a query writes that word in
  back quotes, as 21.3 says one is written. The scenarios after the register settle
  what a register cannot: the words peq reserves, the words it spells, the functions
  it offers, the statuses it reports, what it settles for itself, and whether the
  subclause numbers this suite points at are numbers at all.

  peq is a GQL-implementation in the sense of 24.5 that reads GQL-programs and
  answers them over a graph it derived from PHP sources. It keeps no graph, so the
  statements that would change one are not implemented and are refused by name; the
  register says so, feature by feature.

  @iso:24.3 @iso:24.5
  Scenario: The conformance register, checked against the standard's own artifact
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.3, 24.5
    Given the conformance register:
      | feature | claimed | description |
      | G002   | no  | Different-edges match mode |
      | G003   | no  | Explicit REPEATABLE ELEMENTS keyword |
      | G004   | yes | Path variables |
      | G005   | no  | Path search prefix in a path pattern |
      | G006   | no  | Graph pattern KEEP clause: path mode prefix |
      | G007   | no  | Graph pattern KEEP clause: path search prefix |
      | G010   | yes | Explicit WALK keyword |
      | G011   | yes | Advanced path modes: TRAIL |
      | G012   | yes | Advanced path modes: SIMPLE |
      | G013   | yes | Advanced path modes: ACYCLIC |
      | G014   | no  | Explicit PATH/PATHS keywords |
      | G015   | no  | All path search: explicit ALL keyword |
      | G016   | no  | Any path search |
      | G017   | no  | All shortest path search |
      | G018   | no  | Any shortest path search |
      | G019   | no  | Counted shortest path search |
      | G020   | no  | Counted shortest group search |
      | G030   | no  | Path multiset alternation |
      | G031   | no  | Path multiset alternation: variable length path operands |
      | G032   | no  | Path pattern union |
      | G033   | no  | Path pattern union: variable length path operands |
      | G035   | yes | Quantified paths |
      | G036   | yes | Quantified edges |
      | G037   | no  | Questioned paths |
      | G038   | yes | Parenthesized path pattern expression |
      | G039   | no  | Simplified path pattern expression: full defaulting |
      | G041   | no  | Non-local element pattern predicates |
      | G043   | yes | Complete full edge patterns |
      | G044   | yes | Basic abbreviated edge patterns |
      | G045   | yes | Complete abbreviated edge patterns |
      | G046   | yes | Relaxed topological consistency: adjacent vertex patterns |
      | G047   | yes | Relaxed topological consistency: concise edge patterns |
      | G048   | no  | Parenthesized path pattern: subpath variable declaration |
      | G049   | no  | Parenthesized path pattern: path mode prefix |
      | G050   | no  | Parenthesized path pattern: WHERE clause |
      | G051   | no  | Parenthesized path pattern: non-local predicates |
      | G060   | yes | Bounded graph pattern quantifiers |
      | G061   | yes | Unbounded graph pattern quantifiers |
      | G074   | yes | Label expression: wildcard label |
      | G080   | no  | Simplified path pattern expression: basic defaulting |
      | G081   | no  | Simplified path pattern expression: full overrides |
      | G082   | no  | Simplified path pattern expression: basic overrides |
      | G100   | no  | ELEMENT_ID function |
      | G110   | no  | IS DIRECTED predicate |
      | G111   | no  | IS LABELED predicate |
      | G112   | no  | IS SOURCE and IS DESTINATION predicate |
      | G113   | no  | ALL_DIFFERENT predicate |
      | G114   | no  | SAME predicate |
      | G115   | no  | PROPERTY_EXISTS predicate |
      | GA01   | no  | IEEE 754 floating point operations |
      | GA03   | no  | Explicit ordering of nulls |
      | GA04   | no  | Universal comparison |
      | GA05   | no  | Cast specification |
      | GA06   | no  | Value type predicate |
      | GA07   | no  | Ordering by discarded binding variables |
      | GA08   | no  | GQL-status objects with diagnostic records |
      | GA09   | yes | Comparison of paths |
      | GB01   | no  | Long identifiers |
      | GB02   | yes | Double minus sign comments |
      | GB03   | yes | Double solidus comments |
      | GC01   | no  | Graph schema management |
      | GC02   | no  | Graph schema management: IF [ NOT ] EXISTS |
      | GC03   | no  | Graph type: IF [ NOT ] EXISTS |
      | GC04   | no  | Graph management |
      | GC05   | no  | Graph management: IF [ NOT ] EXISTS |
      | GD01   | no  | Updatable graphs |
      | GD02   | no  | Graph label set changes |
      | GD03   | no  | DELETE statement: subquery support |
      | GD04   | no  | DELETE statement: simple expression support |
      | GE01   | no  | Graph reference value expressions |
      | GE02   | no  | Binding table reference value expressions |
      | GE03   | no  | Let-binding of variables in expressions |
      | GE04   | no  | Graph parameters |
      | GE05   | no  | Binding table parameters |
      | GE06   | no  | Path value construction |
      | GE07   | yes | Boolean XOR |
      | GE08   | no  | Reference parameters |
      | GE09   | yes | Horizontal aggregation |
      | GF01   | no  | Enhanced numeric functions |
      | GF02   | no  | Trigonometric functions |
      | GF03   | no  | Logarithmic functions |
      | GF04   | no  | Enhanced path functions |
      | GF05   | no  | Multi-character TRIM function |
      | GF06   | no  | Explicit TRIM function |
      | GF07   | no  | Byte string TRIM function |
      | GF10   | no  | Advanced aggregate functions: general set functions |
      | GF11   | no  | Advanced aggregate functions: binary set functions |
      | GF12   | no  | CARDINALITY function |
      | GF13   | yes | SIZE function |
      | GF20   | no  | Aggregate functions in sort keys |
      | GG01   | no  | Graph with an open graph type |
      | GG02   | no  | Graph with a closed graph type |
      | GG03   | no  | Graph type inline specification |
      | GG04   | no  | Graph type like a graph |
      | GG05   | no  | Graph from a graph source |
      | GG20   | no  | Explicit element type names |
      | GG21   | no  | Explicit element type key label sets |
      | GG22   | no  | Element type key label set inference |
      | GG23   | no  | Optional element type key label sets |
      | GG24   | no  | Relaxed structural consistency |
      | GG25   | no  | Relaxed key label set uniqueness for edge types |
      | GG26   | no  | Relaxed property value type consistency |
      | GH01   | no  | External object references |
      | GH02   | yes | Undirected edge patterns |
      | GL01   | no  | Hexadecimal literals |
      | GL02   | no  | Octal literals |
      | GL03   | no  | Binary literals |
      | GL04   | yes | Exact number in common notation without suffix |
      | GL05   | yes | Exact number in common notation or as decimal integer with suffix |
      | GL06   | yes | Exact number in scientific notation with suffix |
      | GL07   | yes | Approximate number in common notation or as decimal integer with suffix |
      | GL08   | yes | Approximate number in scientific notation with suffix |
      | GL09   | yes | Optional float number suffix |
      | GL10   | yes | Optional double number suffix |
      | GL11   | yes | Opt-out character escaping |
      | GL12   | no  | SQL datetime and interval formats |
      | GP01   | no  | Inline procedure |
      | GP02   | no  | Inline procedure with implicit nested variable scope |
      | GP03   | no  | Inline procedure with explicit nested variable scope |
      | GP04   | no  | Named procedure calls |
      | GP05   | no  | Procedure-local value variable definitions |
      | GP06   | no  | Procedure-local value variable definitions: value variables based on simple expressions |
      | GP07   | no  | Procedure-local value variable definitions: value variable based on subqueries |
      | GP08   | no  | Procedure-local binding table variable definitions |
      | GP09   | no  | Procedure-local binding table variable definitions: binding table variables based on simple expressions or references |
      | GP10   | no  | Procedure-local binding table variable definitions: binding table variables based on subqueries |
      | GP11   | no  | Procedure-local graph variable definitions |
      | GP12   | no  | Procedure-local graph variable definitions: graph variables based on simple expressions or references |
      | GP13   | no  | Procedure-local graph variable definitions: graph variables based on subqueries |
      | GP14   | no  | Binding tables as procedure arguments |
      | GP15   | no  | Graphs as procedure arguments |
      | GP16   | no  | AT schema clause |
      | GP17   | no  | Binding variable definition block |
      | GP18   | no  | Catalog and data statement mixing |
      | GQ01   | no  | USE graph clause |
      | GQ02   | yes | Composite query: OTHERWISE |
      | GQ03   | yes | Composite query: UNION |
      | GQ04   | yes | Composite query: EXCEPT DISTINCT |
      | GQ05   | no  | Composite query: EXCEPT ALL |
      | GQ06   | yes | Composite query: INTERSECT DISTINCT |
      | GQ07   | no  | Composite query: INTERSECT ALL |
      | GQ08   | yes | FILTER statement |
      | GQ09   | yes | LET statement |
      | GQ10   | no  | FOR statement: list value support |
      | GQ11   | no  | FOR statement: WITH ORDINALITY |
      | GQ12   | yes | ORDER BY and page statement: OFFSET clause |
      | GQ13   | yes | ORDER BY and page statement: LIMIT clause |
      | GQ14   | yes | Complex expressions in sort keys |
      | GQ15   | yes | GROUP BY clause |
      | GQ16   | yes | Pre-projection aliases in sort keys |
      | GQ17   | no  | Element-wise group variable operations |
      | GQ18   | no  | Scalar subqueries |
      | GQ19   | no  | Graph pattern YIELD clause |
      | GQ20   | no  | Advanced linear composition with NEXT |
      | GQ21   | no  | OPTIONAL: Multiple MATCH statements |
      | GQ22   | no  | EXISTS predicate: multiple MATCH statements |
      | GQ23   | no  | FOR statement: binding table support |
      | GQ24   | no  | FOR statement: WITH OFFSET |
      | GS01   | no  | SESSION SET command: session-local graph parameters |
      | GS02   | no  | SESSION SET command: session-local binding table parameters |
      | GS03   | no  | SESSION SET command: session-local value parameters |
      | GS04   | no  | SESSION RESET command: reset all characteristics |
      | GS05   | no  | SESSION RESET command: reset session schema |
      | GS06   | no  | SESSION RESET command: reset session graph |
      | GS07   | no  | SESSION RESET command: reset time zone displacement |
      | GS08   | no  | SESSION RESET command: reset all session parameters |
      | GS10   | no  | SESSION SET command: session-local binding table parameters based on subqueries |
      | GS11   | no  | SESSION SET command: session-local value parameters based on subqueries |
      | GS12   | no  | SESSION SET command: session-local graph parameters based on simple expressions or references |
      | GS13   | no  | SESSION SET command: session-local binding table parameters based on simple expressions or references |
      | GS14   | no  | SESSION SET command: session-local value parameters based on simple expressions |
      | GS15   | no  | SESSION SET command: set time zone displacement |
      | GS16   | no  | SESSION RESET command: reset individual session parameters |
      | GT01   | no  | Explicit transaction commands |
      | GT02   | no  | Specified transaction characteristics |
      | GT03   | no  | Use of multiple graphs in a transaction |
      | GV01   | no  | 8 bit unsigned integer numbers |
      | GV02   | no  | 8 bit signed integer numbers |
      | GV03   | no  | 16 bit unsigned integer numbers |
      | GV04   | no  | 16 bit signed integer numbers |
      | GV05   | no  | Small unsigned integer numbers |
      | GV06   | no  | 32 bit unsigned integer numbers |
      | GV07   | no  | 32 bit signed integer numbers |
      | GV08   | no  | Regular unsigned integer numbers |
      | GV09   | no  | Specified integer number precision |
      | GV10   | no  | Big unsigned integer numbers |
      | GV11   | no  | 64 bit unsigned integer numbers |
      | GV12   | yes | 64 bit signed integer numbers |
      | GV13   | no  | 128 bit unsigned integer numbers |
      | GV14   | no  | 128 bit signed integer numbers |
      | GV15   | no  | 256 bit unsigned integer numbers |
      | GV16   | no  | 256 bit signed integer numbers |
      | GV17   | yes | Decimal numbers |
      | GV18   | no  | Small signed integer numbers |
      | GV19   | no  | Big signed integer numbers |
      | GV20   | no  | 16 bit floating point numbers |
      | GV21   | no  | 32 bit floating point numbers |
      | GV22   | no  | Specified floating point number precision |
      | GV23   | no  | Floating point type name synonyms |
      | GV24   | yes | 64 bit floating point numbers |
      | GV25   | no  | 128 bit floating point numbers |
      | GV26   | no  | 256 bit floating point numbers |
      | GV30   | no  | Specified character string minimum length |
      | GV31   | no  | Specified character string maximum length |
      | GV32   | no  | Specified character string fixed length |
      | GV35   | no  | Byte string types |
      | GV36   | no  | Specified byte string minimum length |
      | GV37   | no  | Specified byte string maximum length |
      | GV38   | no  | Specified byte string fixed length |
      | GV39   | no  | Temporal types: date, local datetime and local time support |
      | GV40   | yes | Temporal types: zoned datetime and zoned time support |
      | GV41   | no  | Temporal types: duration support |
      | GV45   | no  | Record types |
      | GV46   | no  | Closed record types |
      | GV47   | no  | Open record types |
      | GV48   | no  | Nested record types |
      | GV50   | yes | List value types |
      | GV55   | yes | Path value types |
      | GV60   | no  | Graph reference value types |
      | GV61   | no  | Binding table reference value types |
      | GV65   | no  | Dynamic union types |
      | GV66   | no  | Open dynamic union types |
      | GV67   | no  | Closed dynamic union types |
      | GV68   | no  | Dynamic property value types |
      | GV70   | no  | Immaterial value types |
      | GV71   | yes | Immaterial value types: null type support |
      | GV72   | no  | Immaterial value types: empty type support |
      | GV90   | no  | Explicit value type nullability |
    Then every feature code is one ISO/IEC 39075 defines
    And every feature is described the way the standard describes it
    And the register answers for every optional feature the standard defines
    And every feature the register claims is stated by a scenario of this suite
    And no scenario states a feature the register does not claim

  @iso:24.3 @iso:24.5
  Scenario: Every scenario says where what it states is published
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.3, 24.5
    Then every scenario cites where what it states is published

  @iso:23.1
  Scenario: Every condition the implementation reports is one the standard defines
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 23.1
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 00000, 02000
    Then every GQLSTATUS the implementation reports is one ISO/IEC 39075 defines
    And every GQLSTATUS carries the condition the standard words for it

  @iso:24.5
  Scenario: Every name the implementation offers a query is a name GQL can write
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.5, 21.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <delimited identifier>
    Then every name the schema offers is one a query can write

  @iso:24.3
  Scenario: Every subclause this suite points at is one the standard numbers
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.3
    Then every subclause a scenario states is one ISO/IEC 39075 numbers

  @iso:24.5
  Scenario: Everything this claim is checked against is what it was published as
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.5
    Then every artifact this specification reads is the one it arrived as

  @iso:24.5 @iso:24.5.3
  Scenario: The implementation spells no word of its own
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.5, 24.5.3
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <reserved word>, <non-reserved word>
    Then every word the implementation spells is one ISO/IEC 39075's grammar writes
    And every function the implementation offers is one ISO/IEC 39075's grammar calls

  @iso:24.5
  Scenario: What the implementation settles for itself, the standard left for it to settle
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 24.5
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IL018
    Given the implementation-defined register:
      | item  | description | peq |
      | ID022 | The default collation. | characters compare by their Unicode code points |
      | ID028 | The effective binary precision of each supported integer type. | INT64 is a 64-bit two's complement integer |
      | ID034 | The effective decimal precision of each decimal type. | a DECIMAL holds the digits of one INT64: 18 always, and 19 up to 9223372036854775807 |
      | ID037 | The effective binary precision and scale of each supported approximate numeric type. | FLOAT64 is an IEEE 754 binary64 number |
      | ID059 | The exact numeric declared type of results of the COUNT function. | INT64 |
      | ID062 | The exact numeric declared type of a non-negative integer specification. | INT64 |
      | ID063 | The numeric declared type of the result of a dyadic arithmetic operator when either operand is approximate numeric. | FLOAT64 |
      | ID064 | The numeric declared type of the result of a dyadic arithmetic operator when both operands are exact numeric. | INT64 when both operands are INT64, DECIMAL otherwise |
      | ID065 | The precision of the result of addition and subtraction of exact numeric types. | the larger of the two scales, in at most 18 digits |
      | ID066 | The precision of the result of multiplication of exact numeric types. | the sum of the two scales, in at most 18 digits |
      | ID067 | The precision and scale of the result of division of exact numeric types. | a whole number for two INT64 operands; otherwise six digits after the point, or more when an operand has more, or fewer when six would not fit, never fewer than an operand has |
      | ID079 | The declared type of an approximate numeric literal. | FLOAT64 |
      | ID086 | The default graph pattern match mode. | REPEATABLE ELEMENTS |
      | ID095 | The exact numeric declared types of the results of the SUM function. | INT64 over INT64 values, DECIMAL otherwise |
      | ID096 | The exact numeric declared types of the results of the AVG function. | DECIMAL, with six digits after the point where they fit |
      | ID097 | The approximate numeric declared types of the results of the SUM and AVG functions. | FLOAT64 |
      | IA011 | Whether rounding or truncating is used on division with an approximate mathematical result. | an exact quotient is truncated |
      | IL010 | The maximum number of digits permitted in an unsigned integer literal. | 19, up to 9223372036854775807 |
      | IL011 | The maximum precision and scale of numbers of numeric types, for each supported kind of number. | INT64 and DECIMAL the 19 digits of a 64-bit integer, FLOAT64 those of IEEE 754 binary64 |
      | IL018 | The maximum value of the upper bound of a general qualifier. | the value of --hops, 10 unless it is given |
      | IS001 | The implicit ordering of NULLs. | the null value sorts before every value, and so last in a descending sort |
      | IV001 | The character repertoire of GQL source text. | Unicode, written in UTF-8 |
    Then every item the register settles is one ISO/IEC 39075 leaves to an implementation
    And every item the register settles is stated by a scenario of this suite
    And no scenario states an item the register does not settle

  @iso:16.11
  Scenario: An upper bound above the implementation's limit is refused before the graph is read
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <upper bound>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IL018
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 42001
    Given the GQL-program "MATCH (a:Method)-[:methodCall]->{1,11}(b:Method) RETURN count(*) AS matched"
    When the program is executed
    Then the GQLSTATUS is "42001"

  @iso:16.11
  Scenario: An upper bound up to the implementation's limit is followed as far as it says
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.11
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en).bnf.xml <upper bound>
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IL018
    Given the GQL-program "MATCH p = (a:Method WHERE a.name = 'first')-[:methodCall]->{1,10}(b) RETURN max(path_length(p)) AS furthest"
    When the program is executed
    Then the result table holds one row "10"

  @iso:16.4
  Scenario: Two path patterns may bind the same edge, since elements may repeat
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 16.4
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-features.xml G002, G003
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID086
    Given the GQL-program "MATCH (a:Method WHERE a.name = 'first')-[e:methodCall]->(b), (c:Method WHERE c.name = 'first')-[f:methodCall]->(d) RETURN count(*) AS matched"
    When the program is executed
    Then the result table holds one row "1"

  @iso:21.2 @iso:21.4
  Scenario: A program is read as Unicode
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2, 21.4
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IV001
    Given the GQL-program "RETURN '顧客' AS name, char_length('顧客') AS length"
    When the program is executed
    Then the result table holds one row "顧客, 2"

  @iso:21.2
  Scenario: An integer literal longer than an INT64 is out of range
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml IL010
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22003
    Given the GQL-program "RETURN 9223372036854775808 AS n"
    When the program is read
    Then the GQLSTATUS is "22003"

  @iso:21.2
  Scenario: A decimal whose digits no 64-bit integer holds is out of range, not approximated
    Source: https://www.iso.org/obp/ui/en/#iso:std:iso-iec:39075:ed-1:v1:en 21.2, 20.21
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-implementation-defined.xml ID034, IL011
    Source: https://standards.iso.org/iso-iec/39075/ed-1/en/ISO_IEC_39075(en)-conditions.xml 22003
    Given the GQL-program "RETURN 0.123456789012345678 * 100 AS n"
    When the program is executed
    Then the GQLSTATUS is "22003"
