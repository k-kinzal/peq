---
name: peq
description: >-
  PHP dependency and blast-radius analysis with the peq CLI. Use when finding
  callers or callees, estimating refactor impact, or querying a PHP graph in GQL.
allowed-tools: Read, Bash, Glob, Grep
---

# peq

peq reads PHP sources into a bidirectional dependency graph. Grep finds strings;
peq finds relations. Do not grep for callers, callees, or `extends` when peq can
answer.

| Question | Command | Detail |
|----------|---------|--------|
| What does this symbol reach? What depends on it? | `peq <symbol>` | [references/inspect.md](references/inspect.md) |
| Any other question about the graph | `peq graph '<query>'` | [references/graph.md](references/graph.md) |

Inspect is one walk from one symbol. Graph is a query over everything. Quote
targets and queries with `'`. On failure, see
[references/troubleshooting.md](references/troubleshooting.md).
