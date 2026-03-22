# peq

[![GitHub Actions](https://github.com/k-kinzal/peq/actions/workflows/ci.yaml/badge.svg)](https://github.com/k-kinzal/peq/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A PHP dependency analysis CLI tool that visualizes dependency relationships between PHP code elements (classes, methods, functions, properties, constants, etc.) as a tree structure.

## Requirements

- PHP 8.3 or higher
- `ext-ast` PHP extension

## Installation

```bash
composer require k-kinzal/peq
```

Or install globally:

```bash
composer global require k-kinzal/peq
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
  -L, --level=LEVEL          Max depth to display (like tree -L)
  -e, --exclude=EXCLUDE      Exclude patterns or directory names (repeatable) (multiple values allowed)
  -h, --help                 Display help for the given command
  -V, --version              Display this application version
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.
