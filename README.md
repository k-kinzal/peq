# peq

[![GitHub Actions](https://github.com/k-kinzal/peq/actions/workflows/ci.yaml/badge.svg)](https://github.com/k-kinzal/peq/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes.

`peq <symbol>` is the human interface: a focused call graph or class dependency tree, chosen from the target. `peq graph '<query>'` is the advanced interface: query the complete graph and its evidence in [GQL](https://www.iso.org/standard/76120.html). Filtering an inspection never removes information from the analyzed graph.

## Requirements

- PHP 8.3, 8.4 or 8.5 to run peq

The code peq reads is a separate question from the runtime peq runs on: peq analyzes
sources written for PHP 5.6 up to 8.5 on any of those runtimes. See
[Analyzed PHP version](#analyzed-php-version).

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

```console
$ peq 'App\Domain\Invoice' src --filter=calls
App\Domain\Invoice
├── App\Domain\Invoice::total
│   └── App\Domain\Money::add
└── App\Domain\Invoice::lines
    └── App\Domain\Money::add (*)
```

```
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
  -O, --output=OUTPUT        Output format (tree|json|dot|table|graph|mermaid)
  -I, --include=INCLUDE      Include patterns (multiple values allowed)
  -E, --exclude=EXCLUDE      Exclude patterns (multiple values allowed)
      --php-version=PHP-VERSION    PHP version the analyzed sources are read as
                                   (default: the version peq runs on)
      --filter=FILTER        Inspection filter (all|calls|depend; default: chosen from target)
      --type=TYPE            Analyzer type (phpstan|native|debug)
      --memory-limit=MEMORY-LIMIT  Memory limit (e.g. 1G, 256M)
```

`--direction used-by` (or `-R`) walks the other way: what depends on the symbol, which is what a change to it can break.

## Inspection filters

| Target | Default | `--filter=calls` | `--filter=depend` |
|--------|---------|------------------|-------------------|
| Method or function | `calls` | Calls from this callable, including possible implementation bodies | Dependencies of this callable and the callables it reaches, grouped by class |
| Class, interface, trait or enum | `depend` | Start from its methods, including inherited methods | Dependencies of the class and its members, grouped by class |
| Property, constant or other symbol | `all` | Only call relations, if any | Dependencies grouped by owning class |

`--filter=all` keeps every relation in the rooted walk, including membership,
signatures, properties and attributes. It restores the unfiltered behavior of
previous versions. Every filter respects direction and depth; `used-by` follows
incoming relations instead. For a method dependency inspection, other methods of
its class are included only when reached by the call traversal.

```bash
peq 'App\Http\Controller::action' src                  # call graph
peq 'App\Http\Controller' src                          # class dependencies
peq 'App\Http\Controller' src --filter=calls           # calls from its methods
peq 'App\Http\Controller::action' src --filter=depend  # dependencies of this action
peq 'App\Http\Controller::action' src --filter=all     # every reachable relation
```

An interface receiver retains the call to its declared method and adds possible
implementation bodies of that method. Unrelated interface methods do not enter a
call inspection. For example, `$this->port->execute()` can produce:

```text
App\Http\Controller::action
├── App\Domain\Port::execute
└── App\Service::execute (possible)
    └── App\Repository::save
```

Tree and table mark inferred branches `(possible)`; JSON and diagrams expose the
`possible-call` relation. These are candidates from the analyzed class hierarchy,
not a claim about which service a DI container selects at runtime. A narrower
interface or intersection constrains the candidates. An inherited body belongs to
the class that declares it; the implementing class is retained as evidence and is
also included in dependency inspections.

## Output formats

| `--output` | What it writes |
|------------|----------------|
| `tree`     | An indented tree, the way `tree` draws a directory. The default. `(*)` marks a symbol expanded elsewhere, `(recursive)` a cycle. |
| `json`     | One entry per symbol: kind, depth, parent, the relations that led to it, and where it is declared. |
| `table`    | One row per symbol: depth, symbol, kind and `file:line`. |
| `graph`    | The graph itself, drawn in the terminal: every symbol once, joined by arrows, as wide as the terminal and as long as it needs to be. |
| `mermaid`  | The same graph as a Mermaid flowchart, for a pull request or a design note. |
| `dot`      | The same graph as a Graphviz digraph. |

```console
$ peq 'App\Domain\Invoice' src --filter=calls --output=graph
                     ┌──▶ App\Domain\Invoice::total ──┐
App\Domain\Invoice ──┤                                ├──▶ App\Domain\Money::add
                     └──▶ App\Domain\Invoice::lines ──┘
```

```console
$ peq 'App\Domain\Invoice' src --filter=calls --output=mermaid
flowchart LR
    n1["App\Domain\Invoice"]
    n2["App\Domain\Invoice::total"]
    n3["App\Domain\Invoice::lines"]
    n4["App\Domain\Money::add"]
    n1 -->|"declaration-method"| n2
    n1 -->|"declaration-method"| n3
    n2 -->|"method-call"| n4
    n3 -->|"method-call"| n4
```

```bash
peq 'App\Domain\Invoice' src --output=dot | dot -Tsvg -o invoice.svg
```

## Querying the graph

`peq graph` takes a query in GQL, the ISO standard query language for graphs, and answers with a table:

```console
$ peq graph 'MATCH (c:Class)-[:declaresMethod]->(m:Method WHERE m.visibility = "public")
             RETURN c.name AS class, count(m) AS methods
             GROUP BY class ORDER BY methods DESC LIMIT 3' src
+-----------------+-----------------+
| class (STRING)  | methods (INT64) |
+-----------------+-----------------+
| TokenReader     | 17              |
| NodeIdGenerator | 17              |
| NameGenerator   | 16              |
+-----------------+-----------------+
```

`peq graph --schema` lists the labels, properties and functions a query can use, each written the way a query has to write it. A few of peq's names spell words GQL reserves — `` `call` ``, `` `Function` ``, `` `value` `` — and are written in back quotes, so quote a query with `'` rather than `"`.

`--output` takes the same formats as above: `graph`, `mermaid` and `dot` draw the part of the graph the answer holds, and `tree` draws the paths it bound. `--hops` sets the largest upper bound a repetition such as `{1,6}` may be written with (10 by default).

For a drawing, return nodes, edges or paths themselves: `RETURN m, t` preserves
graph elements, while `RETURN m.id, t.id` returns strings. For a tree, bind and return
a path:

```bash
peq graph 'MATCH (m:Method)-[:methodCall]->(t) RETURN m, t' src --output=graph
peq graph 'MATCH p = (m:Method)-[:methodCall]->(t) RETURN p' src --output=tree
```

A nonempty result without elements the requested format can draw exits with code 1
and explains on stderr how to return elements or switch to `--output=table` or
`--output=json`. This also applies to `--schema`, which needs table or JSON output.
A query with no rows still succeeds (exit code 0) and reports `[02000] note: no data`
on stderr. JSON carries that status in its output document instead. Diagnostics
stay out of stdout so they do not become part of a redirected diagram.

### Calls, arguments, implementations and attributes

The full graph distinguishes source calls from possible dispatches:

| Edge label | Meaning | Additional properties |
|------------|---------|-----------------------|
| `methodCall` (also `` `call` ``) | A named receiver call or an unresolved occurrence | `resolution`, `declaredTarget`, `receiverType` when known; `expression` when unresolved |
| `possibleCall` | A possible implementation body for a source call | `resolution = "possible"`, `declaredTarget`, `receiverType`, `implementationType`, `basis = "class-hierarchy"` |
| `callableReference` | First-class callable creation such as `target(...)`, without an invocation | The same source facts as calls; `callableReference = TRUE` |
| `declaresClosure` | Lexical containment of a closure or arrow function | Its declaration location |
| `attribute` | One attribute occurrence | `arguments` (written expressions), `` `parameter` `` when attached to a parameter |

`possibleCall` belongs to `usage`, separately from the source-call family. Select
`` :`call` `` for source calls alone, or `` :`call`|possibleCall `` to follow
implementation bodies as inspect does. Use reverse arrows to find their callers.
Calls and attributes carry `file`, `line`, `column` and a byte `offset` when known.
Repeated occurrences, even on the same line, have distinct edge identities.
Consumers should treat edge IDs as opaque and use `DISTINCT` when counting symbols
rather than occurrences. No query-language extension is needed for these facts.

Every recorded function call, method call, static call and instantiation keeps its
original `expression` and ordered `arguments`. These are source text, not evaluated
values: `$value`, `'literal'`, `Config::MODE` and `1 + 2` keep their spelling, as do
named arguments (`mode: Config::MODE`), unpacking (`...$items`), parentheses and
spacing inside expressions. `argumentNames` and `argumentTypes` align with that
list; an unknown name or type is `NULL`. Types describe the written argument
expression and are limited to syntax-certain strings, integers, floats, booleans,
null and arrays. Variables and constant references are not evaluated or assigned
inferred argument types. An unpacked array is still one written argument.

`argumentCount` counts written arguments. `callSite` identifies one written site,
shared by all its possible targets, and `endOffset` is its inclusive ending byte
offset. Even nested calls sharing a starting position have separate sites. Site
IDs describe the current source snapshot and can change when a file is edited.

```bash
peq graph 'MATCH (caller)-[e:`call`]->(callee)
           RETURN caller.id, callee.id, e.callSite, e.expression,
                  e.arguments, e.argumentNames, e.argumentTypes,
                  e.file, e.line, e.column' src
```

With `--filter=calls` or `--filter=all`, all output formats retain individual call
occurrences: JSON adds a `calls` list to each affected traversal entry, tree and
table show written calls, DOT and Mermaid label each occurrence, and the terminal
graph lists occurrences below its shared wiring. `--filter=depend` aggregates
repeated relations between owners before formatting, keeping dependency reports
concise. Reverse traversal retains the same source facts.

Closures and arrow functions are separate `Closure` / `Callable` nodes, named
`Enclosing::method{closure@line:column}` (or `function{closure@line:column}`). Calls
inside them originate there, not at the enclosing named callable. The
`enclosingSymbol` property identifies that named owner on both nodes and sites.
Inspection follows `declaresClosure` for navigation; this is containment, not
proof that a closure executes. GQL callers can explicitly include this label when
walking lexical scopes. Dependency inspection folds the scopes back onto their
owners. First-class callable references participate in dependencies and the full
graph, but do not count as invocations in the `calls` filter or the `call` label.
Resolution of dynamic function names and arbitrary callback invocation remains
outside the existing target resolver.

Find methods downstream from an action that call PDO, then retrieve their method
attributes (excluding parameter attributes):

```bash
peq graph 'MATCH TRAIL
             (entry:Method WHERE entry.id = "App\Http\Controller::action")
             -[:`call`|possibleCall]->{0,}(m:Method)
             -[:methodCall]->(pdo:Method WHERE pdo.owner = "PDO")
           MATCH (m)-[a:attribute]->(attributeClass)
           WHERE a.`parameter` IS NULL
           RETURN DISTINCT m.id, attributeClass.id, a.arguments, a.file, a.line, a.offset' src
```

Inspect a candidate's evidence separately:

```bash
peq graph 'MATCH (caller)-[e:possibleCall]->(body)
           RETURN caller.id, body.id, e.declaredTarget, e.receiverType,
                  e.implementationType, e.basis, e.file, e.line' src
```

Both engines use the same receiver resolution: declared parameter and property
types (including promoted and inherited properties), declared return types, named
`new` expressions, and simple local assignments. Nullable, union and intersection
types are supported. Assignment inference is flow-insensitive and collects known
alternatives. It does not execute factories, read DI container configuration or
perform PHPStan's full control-flow analysis or resolve arbitrary dynamic values.
PHPDoc receiver types are described below. Unknown receivers and dynamic method names
remain unresolved call occurrences, queryable with `e.resolution = "unresolved"`.
Their identifiers use `unresolved-call@file:line:column`, for example
`unresolved-call@/app/Controller.php:12:5`. Lines and columns are 1-based and point
to the start of the call expression. Columns count bytes within the line, with a
tab counting as one byte; the file-wide byte offset remains available as `offset`.
External methods such as `PDO::query` remain named unresolved symbols when their
sources were not analyzed. Instantiation is a separate `instantiation` relation;
`calls` follows explicit method, static and function calls, not constructor or
callback invocations inferred from other operations.

## Analyzers

| `--type`  | What it does | Sources it reads | Where it is available |
|-----------|--------------|------------------|-----------------------|
| `phpstan` | Builds the graph from PHPStan's analysis. The reference engine. | PHP 7.1 – 8.5 | Installed from source or via Composer |
| `native`  | Reads the sources directly with a parser. Checked against the reference engine to build the same graph. | PHP 5.6 – 8.5 | Everywhere, including the released PHAR |

The released PHAR carries only `native`.

Both engines read PHPDoc annotations using PHPStan's PHPDoc grammar. Class names
written in `@param`, `@return`, `@var`, `@throws`, magic member declarations,
templates, generic inheritance, type aliases, mixins and assertion annotations
contribute `phpdoc` dependencies (the `phpDoc` label in GQL). This includes nested
arrays, shapes, callables, unions, intersections and conditional types. Imports and
local type names are resolved in the comment's lexical scope. An imported type
alias points to its exporting class, whose documentation records the alias's types.

These relations describe the dependencies written in documentation. They retain
the annotation's line and byte offset and participate in `--filter=depend`, `--filter=all` and
reverse traversal. They do not change PHP signatures, create magic method bodies,
or apply PHPStan's type checking and control-flow narrowing. Both ordinary and
prefixed tags retain their written dependencies. Metadata tags and prose do not
create type references; a malformed tag does not discard neighbouring valid tags.
Every consecutive Docblock contributes its own occurrences. A `@var` on a promoted
parameter belongs to the promoted property. Documentation on a trait member is
attributed to the trait when that member has no separate graph symbol.

PHPDoc also supplies receiver types for both engines:

| Source of a value | How calls use its documentation |
|-------------------|---------------------------------|
| Parameters and closure parameters | `@param`, `@phan-param`, `@psalm-param`, `@phpstan-param` |
| Properties and local variables | `@var` and its supported prefixes; promoted constructor `@param` types |
| Function and method results | `@return` and its supported prefixes, including inherited documentation and methods imported from traits |
| Magic members | Readable `@property` / `@property-read` and `@method` result types |
| Containers | Array offsets, shape keys and `foreach` values use the element type |
| Local type names | Template bounds and local/imported aliases retain their declaring namespace |

For example, `/** @param Service $service */ function run($service) { $service->work(); }`
records a call to `Service::work`. With `list<Service>`, only an element such as
`$service[0]` has that receiver type: the list itself is not a `Service`.
PHPStan-prefixed parameter, variable and return types take precedence over Psalm,
Phan and ordinary tags, independently of their order. All written tags still
contribute documentation dependencies, including overridden annotations.

This is source-based receiver resolution, not PHPStan's complete type inference.
Assertion-based branch narrowing, output-parameter effects after calls, generic
argument inference, extensions and framework-specific dynamic return types are not
applied by either peq engine. Their written type references remain dependencies;
metadata such as purity and invocation timing does not name a dependency.

```bash
peq graph 'MATCH (s)-[:phpDoc]->(t) RETURN s.id, t.id' src
composer test:diff
```

The difference suite in `tests/Diff/` checks the
[PHPDoc annotations](https://phpstan.org/writing-php-code/phpdocs-basics) and
[type syntax](https://phpstan.org/writing-php-code/phpdoc-types) supported by the
installed PHPStan version. It compares complete canonical graphs, asserts the
expected documented types, source locations and actual call targets, and runs native
analysis in a separate process so PHPStan's bundled parser cannot mask differences in the parser
shipped with the PHAR. The small `phpstan/phpdoc-parser` library is a runtime
dependency; the `phpstan/phpstan` analysis engine remains a development dependency.

The suite also checks selected types against PHPStan's own PHPDoc resolver, outside
peq's shared enrichment pass. A coverage test reads the installed parser and
PHPStan tag resolver and requires an executable dependency fixture for every tag,
including explicit negative cases for metadata. Every upstream type AST shape must
also occur in the corpus. Dependency upgrades therefore fail the suite when the
annotation vocabulary grows without corresponding cases. Graph equality alone is
not sufficient: two engines omitting the same edge must fail the expected-target
assertions.

## Analysis cache

`peq <symbol>` and `peq graph` automatically share `.peq.cache/` in the current
working directory. An unchanged project reuses its completed graph, skipping
parsing, declaration resolution, PHPStan analysis and call enrichment. Changing the
symbol, query, direction, depth, filters or output format reuses the same analysis.

The cache keeps separate phases:

- **Syntax, per file:** parsed statements with names resolved. Unchanged files are
  reused after edits elsewhere, including when rebuilding the graph. Anonymous
  class identities and project-wide declaration indexes are rebuilt from this syntax.
- **Dependency graph, per engine and selection:** the native source walk or PHPStan
  collector results assembled into a graph, including PHPDoc dependencies and before
  call enrichment.
- **Completed graph:** receiver calls and possible dispatch targets, ready for either
  command. Losing this phase still allows the preceding graph phase to be reused.

Every invocation discovers files and hashes their contents. Additions, deletions,
renames and edits invalidate affected entries even when size and timestamps are
unchanged. Graphs also depend on the working directory, selected files, target PHP
version, runtime and Composer/autoload sources. PHPStan fingerprints external source
contents; native tracks their availability, because it only asks whether external
classes exist. Changes to these inputs invalidate the whole graph conservatively;
file-local syntax remains reusable. PHPStan's own
parser/container cache remains managed by PHPStan.

The `version` file records the peq executable that created the cache. A different
peq version discards **all phases** before rebuilding; no cache-format migration is
attempted. Released PHARs use their embedded release version. Source installations
use a content digest of peq's sources, configuration and dependency lock file, so
local implementation changes invalidate the cache too.

Graph entries are streamed in small batches. Inverse edges and lookup indexes are
rebuilt when an entry is read, so cache writes and reads do not allocate a serialized
copy of the entire graph. Native analysis indexes declaration locations and loads
syntax one file at a time; the graph itself still grows with the symbols and call
occurrences in the selected sources.

Add `.peq.cache/` to your project's `.gitignore`. Delete that directory to force a
fresh analysis. Damaged entries are recomputed, and an unavailable cache location
falls back to ordinary analysis. Writes are atomic and version changes are locked
across concurrent commands. Direct analyzer instances remain uncached unless given
a `PhaseCache`, keeping tests and equivalence checks independent of previous runs.

## Analyzed PHP version

The PHP version peq runs on and the PHP version the analyzed code is written for are
two different things. peq needs PHP 8.3 or newer to run, and reads sources written for
PHP 5.6 through 8.5 — the range depending on the engine, as the table above says.

By default peq reads sources as the version it runs on. Tell it otherwise when the
code is older:

```bash
peq 'Legacy\Invoice::total' . --php-version=5.6 --type=native
```

Getting this right matters, because the version decides what a source is allowed to
say. `match` is a method name in PHP 7 and a keyword in PHP 8; `$text{0}` is a string
offset up to PHP 7.4 and a syntax error after it; `$invoice =& new Invoice()` is PHP 5
and nothing later; an enum is PHP 8.1 and nothing earlier. A file the chosen version
cannot read is left out of the graph the way a file PHP itself would refuse is, so a
symbol that should be there and is not is the sign to check the version — which is
what peq says when it cannot find one:

```
Symbol "Legacy\Invoice::total" is not in the dependency graph, which was read as PHP 8.5.
Check the spelling, the analyzed path, the include and exclude patterns, and the PHP
version the sources are written for.
```

## Configuration

Create a `.peq.yaml` file in your project root to set default options:

```yaml
type: native
output: tree
hops: 10
phpVersion: '7.4'
excludes:
  - vendor
  - vendor-bin
  - .git
  - tests
```

`filter: all` or `PEQ_FILTER=all` sets a persistent inspection preference; omit it
to keep the target-dependent default. The filter never constrains `peq graph`.

Quote the version: unquoted, YAML reads `8.10` as the number `8.1`, which is a
different PHP version.

Configuration is resolved by merging 4 layers (later layers override earlier ones):

1. Default values
2. Environment variables (`PEQ_*`, for example `PEQ_PHP_VERSION=7.4`)
3. YAML config file (`.peq.yaml`)
4. CLI options

## License

This project is licensed under the MIT License - see the LICENSE file for details.
