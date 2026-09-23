# peq

[![GitHub Actions](https://github.com/k-kinzal/peq/actions/workflows/ci.yaml/badge.svg)](https://github.com/k-kinzal/peq/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes.

`peq <symbol>` draws what one symbol reaches. `peq graph '<query>'` answers a question about the whole dependency graph, written in [GQL](https://www.iso.org/standard/76120.html).

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
$ peq 'App\Domain\Invoice' src
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
      --type=TYPE            Analyzer type (phpstan|native|debug)
      --memory-limit=MEMORY-LIMIT  Memory limit (e.g. 1G, 256M)
```

`--direction used-by` (or `-R`) walks the other way: what depends on the symbol, which is what a change to it can break.

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
$ peq 'App\Domain\Invoice' src --output=graph
                     ┌──▶ App\Domain\Invoice::total ──┐
App\Domain\Invoice ──┤                                ├──▶ App\Domain\Money::add
                     └──▶ App\Domain\Invoice::lines ──┘
```

```console
$ peq 'App\Domain\Invoice' src --output=mermaid
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

## Analyzers

| `--type`  | What it does | Sources it reads | Where it is available |
|-----------|--------------|------------------|-----------------------|
| `phpstan` | Builds the graph from PHPStan's analysis. The reference engine. | PHP 7.1 – 8.5 | Installed from source or via Composer |
| `native`  | Reads the sources directly with a parser. Between 13x and 59x faster, and checked to build the same graph. | PHP 5.6 – 8.5 | Everywhere, including the released PHAR |

The released PHAR carries only `native`.

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

Quote the version: unquoted, YAML reads `8.10` as the number `8.1`, which is a
different PHP version.

Configuration is resolved by merging 4 layers (later layers override earlier ones):

1. Default values
2. Environment variables (`PEQ_*`, for example `PEQ_PHP_VERSION=7.4`)
3. YAML config file (`.peq.yaml`)
4. CLI options

## License

This project is licensed under the MIT License - see the LICENSE file for details.
