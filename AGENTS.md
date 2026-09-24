# Agents

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes. `peq <symbol>` draws what one symbol reaches, as a tree, for a person; `peq graph '<query>'` answers whatever can be written in GQL, as a table, for a program or an agent. Inspect defaults to calls for methods/functions and class dependencies for class-like targets; `--filter=all|calls|depend` changes the projection. Graph always exposes the full graph, including source occurrences and possible dispatch evidence. Both write the same set of formats.

## Supported Versions

- **PHP peq runs on**: 8.3 / 8.4 / 8.5 — write source as a PHP 8.3 project: typed class
  constants, `readonly` classes, `#[\Override]`, and the rest of what 8.3 offers
- **PHP peq analyzes**: 5.6 – 8.5 with `native`, 7.1 – 8.5 with `phpstan` — the floor of
  each engine is the floor of what it is built on, and every version in range must keep
  working whichever runtime peq runs on

## Core Concepts

- **Impact analysis is the primary goal** — automatically identify what breaks when a class, method, or function changes
- **Bidirectional traversal** — walk the graph in two directions: `uses` (what the target depends on) and `used-by` (what depends on the target)
- **Reporter switching** — `--output` picks how one walk is written down: `tree` for a person, `json` for a program or an agent, `dot` for a renderer, `table` for a review, `graph` for the wiring drawn in the terminal, `mermaid` for the same drawing on a page. Where the walk stops is decided once, by `Expansion`, so no format can disagree with another about what is affected
- **GQL is the query language** — `peq graph` runs ISO/IEC 39075 GQL, the standard the SQL committee publishes, so an agent already knows it; an invented syntax would have to be explained in every prompt. It runs that and nothing more: a word the standard does not define is a word peq refuses, which `composer spec` checks against ISO's own grammar artifact. Read-only by design: nothing in the language can change a graph peq has just read out of source code
- **Two engines, one graph** — `PhpStanAnalyzer` is the reference; `NativeAnalyzer` reads sources directly and is checked against it by comparing canonical graph snapshots. A change to either must keep them identical
- **The binary carries one engine** — `phpstan/phpstan` is a dev dependency, so the PHAR holds only `NativeAnalyzer`. `AnalyzerKind` offers a kind only when what it is built on is installed
- **Graph model** — bidirectional adjacency list of nodes (Class, Method, Function, etc. — 11 kinds) and edges (MethodCall, Extends, PhpDoc, etc. — 24 kinds). Inverse edges (UsedBy, DeclaredIn) are generated automatically when an edge is added

## Project Tradeoff Sliders

- Scope     ●————————→ HIGH — Deliver the full intended scope; do not cut corners or skip requirements.
- Quality   ●————————→ HIGH — Quality is the top priority. Correctness, test coverage, and strict static analysis come first.
- Time      ←————————● LOW — There is no deadline pressure. Take the time needed to get it right.
- Cost      ←————————● LOW — Resource constraints are not a concern. Invest in doing things properly.

When in doubt, prioritize quality over everything else. It is better to ship less with confidence than to ship more with uncertainty.

## Architecture

Pipeline: `CLI input → Config stacking → Action → Analyzer → Graph → Traversal → Reporter → output`

| Layer | Responsibility | Key file |
|-------|---------------|----------|
| **Command** | IO only — parse arguments, delegate output | `src/Command/InspectCommand.php`, `src/Command/GraphCommand.php` |
| **Config** | Merge 4 layers: Default → Env(`PEQ_*`) → YAML → CLI | `src/Config/ConfigLoader.php` |
| **Action** | Orchestrate Analyzer and Reporter | `src/Action/Inspect/InspectAction.php`, `src/Action/Query/QueryAction.php` |
| **Analyzer** | Parse source code → build Graph | `src/Analyzer/` |
| **Equivalence** | Compare two graphs in canonical form | `src/Analyzer/Graph/GraphSnapshot.php` |
| **Gql** | Read a GQL query and run it against the graph | `src/Gql/Execution/QueryExecution.php` |
| **Reporter** | Format graph or query result into output | `src/Reporter/` |

Dependencies between layers flow top-down only. Command never calls Analyzer directly.

## Project Structure

```
src/
├── Command/         # CLI commands (IO only)
├── Action/          # Use-case orchestration
├── Analyzer/        # Analysis engines and graph model
│   ├── Graph/       # Node, Edge, NodeKind, EdgeKind, GraphSnapshot
│   ├── PhpStanAnalyzer/  # Reference engine, built on PHPStan
│   └── NativeAnalyzer/   # Same graph, read straight from the sources
├── Config/          # Layered configuration readers
├── Gql/             # The query language, from text to answer
│   ├── Lexing/      # Query text into its pieces
│   ├── Syntax/      # What a query is, as a shape
│   ├── Parsing/     # Pieces into that shape
│   ├── Datum/       # The values a query holds, and how they compare
│   ├── Argument/    # Reading a value as the kind an operation needs
│   ├── Binding/     # The rows a clause is given and produces
│   ├── Element/     # The analysed graph as labelled elements a query can match
│   ├── Evaluation/  # Operators, under three-valued logic
│   ├── Invocation/  # The built-in functions and the aggregates
│   ├── Matching/    # Finding every way a pattern matches
│   ├── Execution/   # Running the clauses, and combining query blocks
│   └── Result/      # The answer: columns, rows and a GQLSTATUS
└── Reporter/        # Output formatters and traversal strategies
    ├── Traversal/   # How the graph is walked
    ├── Diagram/     # Drawing a graph — in the terminal or as Mermaid — shared by both commands
    │   └── Layout/  # Where the symbols and the arrows between them go
    ├── TreeReporter/     # An indented tree, for a person
    ├── JsonReporter/     # A JSON document, for a program
    ├── DotReporter/      # A Graphviz digraph, for a renderer
    ├── TableReporter/    # A table of rows, for a review
    ├── GraphReporter/    # The graph itself, drawn, for the wiring
    └── Query/            # The same formats, for what a query answered
tests/               # Mirrors src/ namespaces. tests/Fixture holds test data, never helpers
spec/                # The Behat specification of ISO/IEC 39075 the query engine is held to
config/              # DI container wiring (services.php)
bin/                 # Entry point (console)
```

## Build & Test Commands

- `composer install` — install dependencies and `vendor-bin/` tools
- `composer test` — run PHPUnit (random order, `APP_ENV=test`). Append `-- tests/App/...` or `--filter testName` to narrow scope. The run needs room: two suites analyse peq's own sources with PHPStan in process, which costs most of a gigabyte each and grows with `src`, so `phpunit.xml.dist` allows 4G
- `composer test:equivalence` — read every installed dependency with both engines and fail on any difference
- `composer test:diff` — compare both engines over PHPStan's PHPDoc annotation and type syntax, with native analysis in an isolated process
- `composer spec` — run the Behat specification of ISO/IEC 39075 against the query engine. Append `-- --tags='@feature:G011'` to run the scenarios that state one feature of the standard
- `composer lint` — run PHP CS Fixer + PHPStan (max level)
- `composer format` — apply PHP CS Fixer
- `composer compile` — build PHAR with Box after lint/tests pass
- `bin/console Namespace\\Class::method /path -L 3 --exclude vendor` — inspect dependencies. Use `--direction=used-by` for reverse traversal, `--type=native` for the faster engine, and `--php-version` when the analyzed sources are older than the runtime
- `bin/console Namespace\\Class::method /path --output=json` — write the same walk as JSON. `--output` takes `tree|json|dot|table|graph|mermaid`
- ``bin/console graph 'MATCH (m:Method)-[:`call`]->(t) RETURN m.id, t.id' /path`` — query the graph in GQL. `--schema` writes the labels, properties and functions a query can use, each spelled the way a query has to write it. Quote a query with `'`: six of peq's own names spell words GQL reserves, so they are written in back quotes, which a double-quoted shell argument would take for command substitution

## Adding an Output Format

A format is one arm of one match. `OutputFormat` is a closed set, `ReporterFactory`
answers every case of it, and a case added without an arm is a static analysis error
rather than a runtime surprise.

What a new reporter must not do is decide where the walk stops. The level bound, the
cycle, the symbol expanded on another branch and the kind with nothing below it are
`Expansion`'s decisions, handed over as a `Continuation`; a reporter chooses what to
draw for each one. That is what makes `tests/Unit/Reporter/ReporterTest.php` — which
runs every reporter over the same graph — a contract rather than a smoke test, and it
is why the formats can be compared line for line.

A format that draws the graph rather than the walk is a `DiagramRenderer`, not a
reporter: `GraphReporter` and the query's `DiagramWriter` both take one, so the
terminal drawing and Mermaid are the same `Diagram` written down two ways, for both
commands. The terminal drawing is laid out in `Reporter/Diagram/Layout`; its one rule
is that two arrows never share a stretch of line, because a reader follows a join
either way and would read a relation that does not exist.

A reporter in a directory of its own needs a `deptrac.yaml` layer of its own. Without
one its boundaries are unenforced while the report still reads zero violations, which
is how the native analyzer shipped unchecked for a release.

## Keeping the Two Engines Identical

`NativeAnalyzer` exists to produce the graph `PhpStanAnalyzer` produces, faster. That
claim is a test, not a comment:

- `tests/Contract/Analyzer/NativeAnalyzerEquivalenceContractTest.php` — both engines over a
  corpus written scenario by scenario from the graph model's own vocabulary
- `tests/Contract/Analyzer/NativeAnalyzerCoverageContractTest.php` — the corpus exercises
  every `NodeKind` and `EdgeKind`, so agreement on it is not vacuous
- `tests/Integration/NativeAnalyzerEquivalenceTest.php` — both engines over peq's own sources
- `tests/Property/NativeAnalyzerEquivalencePropertyTest.php` — both engines over drawn
  programs, shrinking any disagreement (`composer test:pbt`)
- `tests/Equivalence/InstalledPackageEquivalenceTest.php` — both engines over every
  installed dependency (`composer test:equivalence`). It sits outside every testsuite in
  `phpunit.xml.dist` on purpose, so `composer test` and the mutation run do not pay for
  it; the `Engine equivalence` workflow does

Changing either engine means re-running all five. A difference that is intended has to
be written down in the README, because a user picking `--type` is choosing between two
answers that are otherwise the same.

PHPDoc changes also run `composer test:diff`. Its fixtures assert actual documented
dependencies as well as graph equality, so two engines ignoring the same annotation
cannot make a case pass.

## Adding to the Query Language

The language is not ours to extend. A clause, an operator or a function that GQL does
not define does not belong in `src/Gql`, however useful it would be: the reason the
command is worth having is that a reader already knows what it accepts, and every
addition of our own makes that less true.

What is ours is the vocabulary a query is written against — the labels a symbol
carries, the properties it offers. Adding one means teaching `NodeLabels`,
`NodeProperties` or their edge counterparts, and adding it to `GraphSchema` so that
`--schema` still answers what a query may write. A name that spells a word GQL reserves
is allowed and costs its user back quotes; `GraphSchema` reports every name already
written that way, so the schema is always something a query can be pasted from.

That rule is enforced rather than trusted, in two places.

`composer spec` runs the conformance claim itself: a Behat specification under `spec/`,
written against ISO/IEC 39075 and its published digital artifacts. ISO's licence for
those grants use, not redistribution, so this MIT-licensed repository does not carry
them: `spec/iso/artifacts.txt` lists where ISO publishes each one and its SHA-256, and
the first run downloads them into `build/iso/` and checks the sums. Clause 24 makes a
conformance claim a register of the optional features implemented, so
`spec/features/conformance.feature` is that register — all 228 of them — and the suite
fails if the register names a feature the standard does not define, omits one it does,
claims a feature no scenario states, or states a feature the register does not claim.
Adding a feature to the claim therefore costs a scenario tagged with its code.

Every scenario cites where what it states is published, on `Source:` lines under its
title — a subclause of the standard, a production of ISO's grammar, a code in one of
its artifacts, or a section of the editors' paper, "Graph Pattern Matching in GQL and
SQL/PGQ" — and a step looks every citation up. A rule nobody can trace to a published
text is one somebody made up; if a behaviour cannot be given a source, it does not
belong in the engine. The implementation-defined register is held to its scenarios the
same way the feature register is: an item is settled exactly when a scenario shows it.

Seven further steps are what keep the language itself honest, each reading what is
published rather than what was remembered:

- the words `ReservedWords::WORDS` holds are `<reserved word>` and `<pre-reserved word>`
  of ISO's grammar artifact, in the order the artifact writes them
- every upper-case word written as a literal anywhere in `src/Gql` is a `<kw>` of that
  same grammar, so an operator of our own fails before it is used
- every function and aggregate peq offers is a name that grammar writes a left
  parenthesis after, which a word merely appearing in it is not: `LABELS` is in there,
  in the syntax for declaring a graph type, and `labels(x)` is still not GQL
- every GQLSTATUS is one ISO's conditions artifact defines, worded as it words it
- everything peq settles for itself is an item ISO's implementation-defined artifact
  leaves to an implementation, named by its code
- every subclause a scenario is tagged with is one `spec/iso/subclauses.txt` numbers,
  because a number beside a familiar title is the easiest thing here to get wrong
- every artifact the six steps above read still has the SHA-256 sum it arrived under,
  which is what stops an artifact being edited until it agrees with `src/`

A new keyword, operator, function or status therefore fails the specification before it
reaches a reader. The specification is the one place the language's behaviour is stated;
PHPUnit tests under `tests/Unit` state what each class does, not what GQL is.

## Writing Tests

A test is written out flat: what it is given, what it does and what it expects, in the
test method, with the expected value written literally. `tests/Fixture` holds test data
— PHP sources to analyse, a sample graph — and never a helper. A test that needs a
helper to be readable is reporting that the interface it tests is hard to use, and a
helper hides that report; write the test out and let the interface be seen. Something
general enough to be worth sharing is a library, and is not called a fixture.
