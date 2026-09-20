# peq

[![GitHub Actions](https://github.com/k-kinzal/peq/actions/workflows/ci.yaml/badge.svg)](https://github.com/k-kinzal/peq/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes. Provides tree output for humans, enabling safe modification of PHP code by understanding what breaks when a class, method, or function changes.

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
  -O, --output=OUTPUT        Output format: tree|json|dot|table (default: tree)
  -I, --include=INCLUDE      Include patterns (multiple values allowed)
  -E, --exclude=EXCLUDE      Exclude patterns (multiple values allowed)
      --php-version=PHP-VERSION    PHP version the analyzed sources are read as
                                   (default: the version peq runs on)
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

```bash
peq 'App\Domain\Invoice' src --output=dot | dot -Tsvg -o invoice.svg
```

A tree has to pick one path to each symbol and mark the rest, so a branch that stops
says why: `(recursive)` for a cycle, `(*)` for a symbol expanded elsewhere. The JSON
says the same thing in a `truncated` field and the table in the symbol cell. A digraph
needs neither, because an arrow arriving twice at the same box is what it is for.

## Analyzers

Two analyzers read real sources, and they are built to describe the same graph:

| `--type`  | What it does | Sources it reads | Where it is available |
|-----------|--------------|------------------|-----------------------|
| `phpstan` | Runs PHPStan over the sources and assembles the graph from what its collectors report. The reference engine. | PHP 7.1 – 8.5 | Installed from source or via Composer |
| `native`  | Reads the sources directly with a parser, resolving names the way PHP does. Between 13x and 59x faster. | PHP 5.6 – 8.5 | Everywhere, including the released PHAR |

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
