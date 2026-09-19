# Agents

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes. Provides tree output for humans via TreeReporter and structured output for AI agents, enabling both to modify PHP code safely.

## Supported Versions

- **PHP**: 8.1 / 8.2 / 8.3 / 8.4 / 8.5

## Core Concepts

- **Impact analysis is the primary goal** — automatically identify what breaks when a class, method, or function changes
- **Bidirectional traversal** — walk the graph in two directions: `uses` (what the target depends on) and `used-by` (what depends on the target)
- **Reporter switching** — swap between human-friendly (tree display) and AI-friendly (structured data) output formats
- **Two engines, one graph** — `PhpStanAnalyzer` is the reference; `NativeAnalyzer` reads sources directly and is checked against it by comparing canonical graph snapshots. A change to either must keep them identical
- **The binary carries one engine** — `phpstan/phpstan` is a dev dependency, so the PHAR holds only `NativeAnalyzer`. `AnalyzerKind` offers a kind only when what it is built on is installed
- **Graph model** — bidirectional adjacency list of nodes (Class, Method, Function, etc. — 11 kinds) and edges (MethodCall, Extends, etc. — 22 kinds). Inverse edges (UsedBy, DeclaredIn) are generated automatically when an edge is added

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
| **Command** | IO only — parse arguments, delegate output | `src/Command/InspectCommand.php` |
| **Config** | Merge 4 layers: Default → Env(`PEQ_*`) → YAML → CLI | `src/Config/ConfigLoader.php` |
| **Action** | Orchestrate Analyzer and Reporter | `src/Action/Inspect/InspectAction.php` |
| **Analyzer** | Parse source code → build Graph | `src/Analyzer/` |
| **Equivalence** | Compare two graphs in canonical form | `src/Analyzer/Graph/GraphSnapshot.php` |
| **Reporter** | Format graph into output | `src/Reporter/` |

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
└── Reporter/        # Output formatters and traversal strategies
tests/               # Mirrors src/ namespaces. Keep fixtures next to the code they test
config/              # DI container wiring (services.php)
bin/                 # Entry point (console)
```

## Build & Test Commands

- `composer install` — install dependencies and `vendor-bin/` tools
- `composer test` — run PHPUnit (random order, `APP_ENV=test`). Append `-- tests/App/...` or `--filter testName` to narrow scope
- `composer test:equivalence` — read every installed dependency with both engines and fail on any difference
- `composer lint` — run PHP CS Fixer + PHPStan (max level)
- `composer format` — apply PHP CS Fixer
- `composer compile` — build PHAR with Box after lint/tests pass
- `bin/console Namespace\\Class::method /path -L 3 --exclude vendor` — inspect dependencies. Use `--direction=used-by` for reverse traversal, and `--type=native` for the faster engine

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
