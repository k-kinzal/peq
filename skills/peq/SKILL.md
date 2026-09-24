---
name: peq
description: >-
  PHP dependency and blast-radius analysis with the peq CLI. Use when finding
  callers or callees, estimating refactor impact, querying a PHP graph in GQL,
  or tracing variable origins and control conditions with experimental inspection.
allowed-tools: Read, Bash, Glob, Grep
---

# peq

peq reads PHP sources into a bidirectional dependency graph. Grep finds strings;
peq finds relations. Do not grep for callers, callees, or `extends` when peq can
answer.

| Question | Command | Detail |
|----------|---------|--------|
| What does this symbol reach? What depends on it? | `peq <symbol>` | [references/inspect.md](references/inspect.md) |
| Filters, counts, joins or paths across the symbol graph | `peq graph '<query>'` | [references/graph.md](references/graph.md) |
| What determines this variable? What does it affect, and under which conditions? | `peq experimental inspect '<target>'` | [references/experimental.md](references/experimental.md) |
| Prepare a report about unresolved or incorrect experimental analysis | `peq experimental issue result.json` | [references/experimental.md](references/experimental.md#reporting-an-analysis-issue) |

Inspect is the easy human interface: a focused walk from one symbol. Methods and
functions default to calls; class-like targets default to class dependencies.
`--filter=all|calls|depend` overrides that choice. Graph is the advanced interface:
a query over every relation and its evidence.

Experimental inspection traces source occurrences with explicit analysis limits;
read its reference before interpreting the result. Quote targets and queries with
`'`, especially variable targets containing `$`. On failure, see
[references/troubleshooting.md](references/troubleshooting.md).
