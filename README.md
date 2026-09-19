# peq

[![GitHub Actions](https://github.com/k-kinzal/peq/actions/workflows/ci.yaml/badge.svg)](https://github.com/k-kinzal/peq/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes.

It answers two kinds of question. `peq <symbol>` draws what one symbol reaches, as a tree, for a person. `peq graph '<query>'` answers whatever you can write in [GQL](https://www.iso.org/standard/76120.html) — the ISO standard query language for graphs — as a table, for a program or an agent.

## Requirements

- PHP 8.1 or higher

## Installation

Download the PHAR from [GitHub Releases](https://github.com/k-kinzal/peq/releases):

```bash
curl -Lo peq.phar https://github.com/k-kinzal/peq/releases/latest/download/peq.phar
chmod +x peq.phar
sudo mv peq.phar /usr/local/bin/peq
```

Or install via Composer:

```bash
composer require --dev k-kinzal/peq
```

## Usage

```
Description:
  Show dependency tree of a PHP function/method/class.

Usage:
  peq [options] [--] <target> [<path>]

Arguments:
  target                     Namespace\ClassName, Namespace\ClassName::methodName or Namespace\functionName
  path                       Base directory to analyze (default: current working dir)

Options:
      --config=CONFIG        Path to config file (default: <cwd>/.peq.yaml)
  -D, --direction=DIRECTION  Dependency direction: uses|used-by (default: uses)
  -R, --reverse              Shortcut for --direction used-by
  -L, --level=LEVEL          Limit depth of the dependency graph
  -O, --output=OUTPUT        Output format: tree|json|dot|table|graph (default: tree)
  -I, --include=INCLUDE      Include patterns (multiple values allowed)
  -E, --exclude=EXCLUDE      Exclude patterns (multiple values allowed)
      --type=TYPE            Analyzer type (phpstan|native|debug)
      --memory-limit=MEMORY-LIMIT  Memory limit (e.g. 1G, 256M)
  -h, --help                 Display help for the given command
  -V, --version              Display this application version
```

## Output formats

Every format describes the same walk — the same root, the same order, the same places
the walk stops. They differ only in what they write down about it.

| `--output` | What it writes | Who reads it |
|------------|----------------|--------------|
| `tree`     | An indented tree, the way `tree` draws a directory. The default. | A person at a terminal |
| `json`     | One entry per symbol: its kind, whether analysis resolved it, the depth it sits at, the symbol it hangs under, the relations that led to it, and where it is declared. | A program or an agent |
| `dot`      | A Graphviz digraph of the symbols the walk reached and every relation between them. | A renderer |
| `table`    | One row per symbol: depth, symbol, kind and `file:line`. | A review |
| `graph`    | The graph itself: every symbol once, numbered, with an arrow to a number for every relation between them. | A person who wants the wiring, not the shape |

```console
$ peq 'App\Domain\Invoice' src
App\Domain\Invoice
├── App\Domain\Invoice::total
│   └── App\Domain\Money::add
└── App\Domain\Invoice::lines
```

```console
$ peq 'App\Domain\Invoice' src --output=table
+-------+---------------------------+--------+------------------------------------+
| Depth | Symbol                    | Kind   | Location                           |
+-------+---------------------------+--------+------------------------------------+
| 0     | App\Domain\Invoice        | class  | /project/src/Domain/Invoice.php:12 |
| 1     | App\Domain\Invoice::total | method | /project/src/Domain/Invoice.php:19 |
| 2     | App\Domain\Money::add     | method | /project/src/Domain/Money.php:24   |
| 1     | App\Domain\Invoice::lines | method | /project/src/Domain/Invoice.php:31 |
+-------+---------------------------+--------+------------------------------------+
```

```json
{
  "direction": "uses",
  "symbol": "App\\Domain\\Invoice",
  "nodes": [
    {
      "id": "App\\Domain\\Invoice::total",
      "kind": "method",
      "resolved": true,
      "depth": 1,
      "parent": "App\\Domain\\Invoice",
      "relations": ["declaration-method"],
      "truncated": null,
      "file": {"path": "/project/src/Domain/Invoice.php", "line": 19, "column": 5}
    }
  ]
}
```

```console
$ peq 'App\Domain\Invoice' src --output=json | jq -r '.nodes[] | select(.truncated == null) | .id'
App\Domain\Invoice
App\Domain\Invoice::total
App\Domain\Money::add
App\Domain\Invoice::lines
```

```console
$ peq 'App\Domain\Invoice' src --output=graph
(1) App\Domain\Invoice [class] src/Domain/Invoice.php:12
    ├── declaration-method ──> (2) App\Domain\Invoice::total
    └── declaration-method ──> (4) App\Domain\Invoice::lines
(2) App\Domain\Invoice::total [method] src/Domain/Invoice.php:19
    └── method-call ──> (3) App\Domain\Money::add
(3) App\Domain\Money::add [method] src/Domain/Money.php:24
(4) App\Domain\Invoice::lines [method] src/Domain/Invoice.php:31
```

```bash
peq 'App\Domain\Invoice' src --output=dot | dot -Tsvg -o invoice.svg
```

A tree has to pick one path to each symbol and mark the rest, so a branch that stops
says why: `(recursive)` for a cycle, `(*)` for a symbol expanded elsewhere. The JSON
says the same thing in a `truncated` field and the table in the symbol cell. A digraph
needs neither, because an arrow arriving twice at the same box is what it is for.

## Querying the graph in GQL

`peq <symbol>` answers one question. `peq graph` answers whatever you can write:

```console
$ peq graph "MATCH (c:Class)-[:declaresMethod]->(m:Method WHERE m.visibility = 'public')
             RETURN c.name AS class, count(m) AS methods
             GROUP BY class ORDER BY methods DESC LIMIT 3" src
+----------------+------------------+
| class (STRING) | methods (INT64)  |
+----------------+------------------+
| TokenReader    | 17               |
| RawConfig      | 12               |
| Graph          | 9                |
+----------------+------------------+
```

The language is [GQL](https://www.iso.org/standard/76120.html), the ISO standard the
SQL committee publishes, as the [Microsoft Fabric guide](https://learn.microsoft.com/fabric/graph/gql-language-guide)
documents it. That choice is the point of the command: the reader it exists for is an
agent deciding whether a change is safe, and an agent that has read about graph
databases has read about GQL. A query language invented here would have to be
explained in every prompt.

```
Usage:
  peq graph [options] [--] [<query> [<path>]]

Arguments:
  query                      The GQL query to run
  path                       Base directory to analyze (default: current working dir)

Options:
      --schema               Write the labels, properties and functions a query can use
      --config=CONFIG        Path to config file (default: <cwd>/.peq.yaml)
  -O, --output=OUTPUT        Output format: table|json|graph|dot|tree (default: table)
      --hops=HOPS            How far a repetition with no upper bound goes (default: 10)
  -I, --include=INCLUDE      Include patterns (multiple values allowed)
  -E, --exclude=EXCLUDE      Exclude patterns (multiple values allowed)
      --type=TYPE            Analyzer type (phpstan|native|debug)
      --memory-limit=MEMORY-LIMIT  Memory limit (e.g. 1G, 256M)
```

### What a query is written against

A symbol carries what it is, the families it belongs to, and whether analysis found it:

| Labels | Carried by |
|--------|------------|
| `Class`, `Interface`, `Trait`, `Enum` | and all of them also carry `ClassLike` |
| `Method`, `Property`, `Constant`, `EnumCase` | and all of them also carry `Member` |
| `Method`, `Function` | and both of them also carry `Callable` |
| `Builtin`, `Unknown` | a PHP builtin, and a symbol outside the analyzed sources |
| `Resolved`, `Unresolved` | whether analysis actually found it |

The families are what make a pattern survive a new kind of symbol: `(:Callable)` covers
methods and functions, and `(:ClassLike&!Interface)` covers the rest of a hierarchy
without enumerating it.

A relation carries the word a sentence about code would use, and its family:

| Labels | Written by |
|--------|------------|
| `functionCall`, `methodCall`, `staticCall` | and all three also carry `call` and `usage` |
| `instantiation`, `propertyAccess`, `staticPropertyAccess`, `constFetch`, `instanceOf`, `catches` | and all of them also carry `usage` |
| `declaresMethod`, `declaresProperty`, `declaresConstant`, `declaresEnumCase` | and all four also carry `declares` and `declaration` |
| `extends`, `implements`, `traitUse`, `attribute`, `propertyType` | and all of them also carry `declaration` |
| `parameterType`, `returnType` | and both also carry `signatureType` and `declaration` |

Properties are everything the analysis knows — including everything the declaration
says, which is what makes a query able to select by visibility, by keyword, or by
attribute:

```console
$ peq graph --schema --output=json | jq -r '.rows[] | select(.[0] == "node property") | "\(.[1]) \(.[2])"'
id STRING
kind STRING
name STRING
namespace STRING
owner STRING
resolved BOOL
file STRING
fileName STRING
line INT64
column INT64
visibility STRING
static BOOL
abstract BOOL
final BOOL
readonly BOOL
deprecated BOOL
attributes LIST<STRING>
type STRING
value STRING
signature STRING
returnType STRING
parameters LIST<STRING>
parameterTypes LIST<STRING>
parameterCount INT64
```

A property the source says nothing about is absent rather than empty, so a pattern
that matches classes and methods together can ask each of them what it knows:
`p.visibility IS NULL` means "this kind of symbol has no visibility", not "it is
package-private".

`--schema` is there so that an agent meeting a codebase for the first time can ask what
it may write, instead of guessing. It answers as a result table like any other, so
`--schema --output=json` is a machine-readable vocabulary.

### Questions it was built for

Which endpoints a change reaches, in a framework that routes by attribute:

```
MATCH (m:Method)-[:call]->{1,4}(t:Method {name: 'save'})
FILTER size(m.attributes) > 0
RETURN DISTINCT m.owner AS controller, m.name AS action, m.attributes AS routes
ORDER BY controller, action
```

What a page-level cache would have to cover — the reads a controller reaches, and
where they are written:

```
MATCH p = (c:Class WHERE c.name ENDS WITH 'Controller')
          -[:declaresMethod]->(:Method)
          -[:call]->{1,5}(read:Method)
FILTER read.name STARTS WITH 'find' OR read.name STARTS WITH 'get'
RETURN c.name AS controller, read.id AS reads, path_length(p) AS hops
ORDER BY controller, hops
```

Which of those reads already cache, and which do not:

```
MATCH (m:Method)-[:call]->{1,3}(cache:Method)
FILTER cache.owner ENDS WITH 'Cache'
RETURN DISTINCT m.id AS cached
UNION ALL
MATCH (m:Method)-[:call]->{1,3}(:Method {name: 'query'})
RETURN DISTINCT m.id AS cached
```

The blast radius of a change, counted rather than drawn:

```
MATCH (t:Method {id: 'App\\Domain\\Money::add'})<-[:call]-{1,6}(caller:Method)
RETURN count(DISTINCT caller) AS reached, count(DISTINCT caller.owner) AS classes
```

### Output

The formats are the root command's, and mean for a table what they mean for a walk:

| `--output` | What it writes |
|------------|----------------|
| `table`    | The result table. The default. |
| `json`     | The status, the columns with their types, and the rows. Symbols and relations serialise as objects with their labels and properties. |
| `graph`    | The piece of graph the answer holds, drawn in the terminal — including the relations between the symbols the query found but did not bind. |
| `dot`      | The same piece of graph, as a Graphviz digraph. |
| `tree`     | The paths the query bound, drawn as one tree with their shared beginnings written once. Writes nothing unless the query bound a path. |

```console
$ peq graph "MATCH p = (a:Method)-[:call]->{1,3}(b:Method) RETURN p" src --output=tree
App\Reporter\Query\DotWriter::report
├── staticCall ──> App\Reporter\Query\DotWriter::arrow
│   └── staticCall ──> App\Reporter\Query\DotWriter::quoted
└── staticCall ──> App\Reporter\Query\ResultElements::of
    ├── staticCall ──> App\Reporter\Query\ResultElements::collectNodes
    └── staticCall ──> App\Reporter\Query\ResultElements::flattened
```

Every answer carries a [GQLSTATUS](https://learn.microsoft.com/fabric/graph/gql-reference-status-codes),
which is the part the standard says a program may test for: `00000` found rows, `02000`
found none, `42001` could not read the query, `42002` named something nothing bound.
Finding nothing is a success and exits zero — a question that has been settled should
not be retried.

### What is and is not implemented

Everything the language guide documents for reading a graph: `MATCH` and `OPTIONAL
MATCH`, `LET`, `FILTER`, `ORDER BY`, `OFFSET`, `LIMIT`, `RETURN` with `DISTINCT` and
`GROUP BY`; label expressions with `&`, `|`, `!` and parentheses; property and `WHERE`
fillers; every arrow form and its shortcut; path variables, path modes (`WALK`,
`TRAIL`, `SIMPLE`, `ACYCLIC`) and variable-length patterns over an edge or a
parenthesised group; three-valued logic, `IS NULL`, `IN`, `CONTAINS`, `STARTS WITH`,
`ENDS WITH`, `CASE`, and the aggregate, string, list, graph, temporal and generic
functions. The set operators the guide lists as not yet supported — `UNION DISTINCT`,
`EXCEPT`, `INTERSECT`, `OTHERWISE` — are supported here.

Three things are deliberately different:

- **A repetition written without an upper bound stops at `--hops`** (ten by default).
  On a graph with cycles the alternative is unbounded work; the reference
  implementation caps it at eight.
- **A projection holding any aggregate is a grouped projection**, so `RETURN a, avg(b)`
  answers once per group rather than once per row. Aggregation along a group list —
  `size(e)`, `avg(e.line)` — falls out of the same rule and works.
- **A run of clauses with no `RETURN` shows everything it bound.** GQL would ask for
  one; a reader exploring a codebase with `MATCH (m:Method WHERE m.deprecated)` should
  be answered rather than corrected.

Not implemented, and not planned: everything that changes a graph (`INSERT`, `SET`,
`REMOVE`, `DELETE`), sessions and transactions, and graph type DDL. peq answers
questions about source code it has just read; a statement that could change the graph
would be describing a codebase that does not exist.

## Analyzers

Two analyzers read real sources, and they are built to describe the same graph:

| `--type`  | What it does | Where it is available |
|-----------|--------------|-----------------------|
| `phpstan` | Runs PHPStan over the sources and assembles the graph from what its collectors report. The reference engine. | Installed from source or via Composer |
| `native`  | Reads the sources directly with a parser, resolving names the way PHP does. Between 13x and 59x faster. | Everywhere, including the released PHAR |

```bash
peq 'App\Domain\Invoice::total' src --type=native
```

**The released PHAR carries only `native`.** Bundling a static analyser to run a parser
is most of the download for none of the answers: the PHAR is 7.2MB where PHPStan alone
is 47MB. `--type` there offers `native|debug` and refuses anything else — the choice a
build can make is the choice it is asked to make. Installed from source or via Composer,
`--type=phpstan` is there and remains the default.

The two are not asked to agree by inspection. `native` is checked against `phpstan` by
comparing the graphs they build of the same sources, reduced to a canonical form: a
corpus of scenarios written to exercise every kind of symbol and relation the graph
model has, peq's own sources, and programs drawn at random by a property test that
shrinks any disagreement to the one expression that causes it.

Every one of those checks runs in CI. A further job reads **every installed dependency**
with both engines and fails on any difference, so a package that arrives tomorrow is read
tomorrow:

```bash
composer test:equivalence
```

Two differences remain, both of them places where the reference engine has no answer
to agree with:

- A file PHP itself would refuse is left out by both engines, silently.
- A `class` declared inside a method body makes PHPStan raise an internal error, which
  peq reports as a failed analysis; `native` reads it.

## Configuration

Create a `.peq.yaml` file in your project root to set default options:

```yaml
type: native
output: tree
hops: 10
excludes:
  - vendor
  - vendor-bin
  - .git
  - tests
```

Configuration is resolved by merging 4 layers (later layers override earlier ones):

1. Default values
2. Environment variables (`PEQ_*`)
3. YAML config file (`.peq.yaml`)
4. CLI options

## License

This project is licensed under the MIT License - see the LICENSE file for details.
