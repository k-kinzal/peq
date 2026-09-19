# peq

[![GitHub Actions](https://github.com/k-kinzal/peq/actions/workflows/ci.yaml/badge.svg)](https://github.com/k-kinzal/peq/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes. Provides tree output for humans, enabling safe modification of PHP code by understanding what breaks when a class, method, or function changes.

## Requirements

- PHP 8.3, 8.4 or 8.5 to run peq

The code peq reads is a separate question from the runtime peq runs on: peq analyzes
sources written for PHP 7.1 up to 8.5 on any of those runtimes. See
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
  -I, --include=INCLUDE      Include patterns (multiple values allowed)
  -E, --exclude=EXCLUDE      Exclude patterns (multiple values allowed)
      --php-version=PHP-VERSION    PHP version the analyzed sources are read as, from 7.1 to 8.5
                                   (default: the version peq runs on)
      --memory-limit=MEMORY-LIMIT  Memory limit (e.g. 1G, 256M)
  -h, --help                 Display help for the given command
  -V, --version              Display this application version
```

## Analyzed PHP version

The PHP version peq runs on and the PHP version the analyzed code is written for are
two different things. peq needs PHP 8.3 or newer to run, and analyzes code written
for **PHP 7.1 through PHP 8.5** — every version in that range, on every runtime peq
supports.

By default peq reads sources as the version it runs on. Tell it otherwise when the
code is older:

```bash
peq 'Legacy\Invoice::total' . --php-version=7.1
```

Getting this right matters, because the version decides what a source is allowed to
say. `match` is a method name in PHP 7 and a keyword in PHP 8; `$text{0}` is a string
offset up to PHP 7.4 and a syntax error after it; an enum is PHP 8.1 and nothing
before it. peq reports a file it could not read as the version it was told, rather
than leaving it out of the graph:

```
PHPStan could not finish analysing /app/src/Invoice.php as PHP 7.1: Syntax error, unexpected T_ENUM on line 5
```

PHP 5.6 and 7.0 sources are read as far as they are also valid PHP 7.1, which most of
them are. The lower bound is the analysis engine's own: PHPStan accepts no analysis
target below 7.1.

## Configuration

Create a `.peq.yaml` file in your project root to set default options:

```yaml
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
