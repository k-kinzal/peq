# Agents

A CLI tool that analyzes PHP code dependencies and visualizes the blast radius of changes. Provides tree output for humans via TreeReporter and structured output for AI agents, enabling both to modify PHP code safely.

## Core Concepts

- **Impact analysis is the primary goal** — automatically identify what breaks when a class, method, or function changes
- **Bidirectional traversal** — walk the graph in two directions: `uses` (what the target depends on) and `used-by` (what depends on the target)
- **Reporter switching** — swap between human-friendly (tree display) and AI-friendly (structured data) output formats
- **Graph model** — bidirectional adjacency list of nodes (Class, Method, Function, etc. — 11 kinds) and edges (MethodCall, Extends, etc. — 22 kinds). Inverse edges (UsedBy, DeclaredIn) are generated automatically when an edge is added

## Architecture

Pipeline: `CLI input → Config stacking → Action → Analyzer → Graph → Traversal → Reporter → output`

| Layer | Responsibility | Key file |
|-------|---------------|----------|
| **Command** | IO only — parse arguments, delegate output | `src/Command/InspectCommand.php` |
| **Config** | Merge 4 layers: Default → Env(`PEQ_*`) → YAML → CLI | `src/Config/ConfigLoader.php` |
| **Action** | Orchestrate Analyzer and Reporter | `src/Action/Inspect/InspectAction.php` |
| **Analyzer** | Parse source code → build Graph | `src/Analyzer/` |
| **Reporter** | Format graph into output | `src/Reporter/` |

Dependencies between layers flow top-down only. Command never calls Analyzer directly.

## Project Structure

```
src/
├── Command/    # CLI commands (IO only)
├── Action/     # Use-case orchestration
├── Analyzer/   # Analysis engine and graph model
│   └── Graph/  # Node, Edge, NodeKind, EdgeKind
├── Config/     # Layered configuration readers
└── Reporter/   # Output formatters and traversal strategies
tests/          # Mirrors src/ namespaces. Keep fixtures next to the code they test
config/         # DI container wiring (services.php)
bin/            # Entry point (console)
```

## Build & Test Commands

- `composer install` — install PHP 8.3 dependencies and `vendor-bin/` tools
- `composer test` — run PHPUnit (random order, `APP_ENV=test`). Append `-- tests/App/...` or `--filter testName` to narrow scope
- `composer lint` — run PHP CS Fixer + PHPStan (max level)
- `composer format` — apply PHP CS Fixer
- `composer compile` — build PHAR with Box after lint/tests pass
- `bin/console Namespace\\Class::method /path -L 3 --exclude vendor` — inspect dependencies. Use `--direction=used-by` for reverse traversal
