# Experimental source and dependency inspection

`peq experimental inspect` preserves a callable's syntax first, then solves explicitly
admitted local dependency rules. It is isolated in `ExperimentAnalyzer`, a copy of the
native symbol pipeline. Production analyzers and their graph are unchanged.

```bash
peq experimental inspect 'Example::render' src --line 42 --variable view --output=json
peq experimental inspect 'Example::render' src --line 20 --reverse --output=tree
peq experimental inspect 'Example::render' src --line 42 --strict --output=json > result.json
peq experimental issue result.json --description 'Expected …; observed …'
```

`--line` is a 1-based absolute source line; `--column` is a 1-based byte column.
`--variable` accepts a name with or without `$`. Without it, all occurrences starting
on that line are selected. Reads and writes have separate identities. Select a declared
function or method, including the declaring trait for a trait method. Anonymous
callables, inherited method lookup and interprocedural expansion are not supported.

All six output formats receive the same slice. `--level` limits dependency edges;
`--reverse` means `--direction=used-by`. Configuration, include/exclude filters, memory
limits and `--php-version` follow standard inspection. There is no `--type` option.

Exit statuses: **0** means an answer was written, **1** means invalid input or failure,
**2** means `--strict` detected incomplete or truncated analysis. Always inspect
`analysis.complete` when consuming JSON without `--strict`.

## Separate syntax facts from inferred dependencies

Schema version 2 has two independent parts:

* `structure` contains the **entire callable AST**, including unused branches, nested
  callable bodies and unsupported constructs. A site has syntax kind, written target
  where statically named, source span/text, parent ID and named child role. An if node
  retains `cond`, `stmts`, `elseifs` and `else`; switch, match and loops retain their own
  named children. A structure edge is lexical containment, **not a proven predicate**.
* `nodes` and `edges` contain the selected inference slice. Unknown regions remain
  explicit nodes connected to available evidence. Source text inside those regions can
  still be selected. Selecting a retained but unvisited occurrence yields `NOT_ANALYZED`.

For `if ($foo) { $foo = new Foo; } else { $foo = new Bar; }`, both written class uses
remain in `structure`. The invocation boundaries have truthy/falsy control edges to
**the earlier read of `$foo`**, whose definition is the input, not either assignment.
Constructor return/effects remain unknown. Looking up a written class name does not
prove that constructing it will succeed.

A nested early return preserves the surviving alternatives. For example,
`if ($a) { if ($b) { return 0; } } return 1;` retains the condition
`($a && !$b) || !$a` using `any-path` and `all-conditions-*` nodes. A complete pair of
complementary branches simplifies away; a condition is not attached to an unconditional
continuation merely because it appeared earlier.

## What is solved

Checked rules cover named local reads/writes, scalar literals, side-effect-free
operators, if/elseif/else, return, echo, local increments/compound assignments,
short-circuit boolean operations, coalescing and ternaries. Whole-variable assignment
replaces previous definitions; a saved copy retains its original definition.
Unrecognized syntax does **not** fall through to eager child evaluation.

These rules describe **local source origins under normal PHP expression completion**.
They do not evaluate runtime values, prove absence of runtime errors, model implicit
exceptions from operators, or guarantee that a syntactically possible path executes.
Conditions sharing variable origins report `PATH_CORRELATION`: combined path feasibility
requires additional reasoning. A resolved dependency result is not a proof that removing
a class, branch or variable is safe.

Loops, switch, match, calls, heap/array access, indirect writes, references, shared
storage, closures, generators, exception handling and dynamic execution are retained as
**Unknown**. The previous optimistic transfers for these constructs were removed.
In particular, `isset($a, $b[$i = 1])`, `assert(++$i)`, `get_defined_vars()` and
`func_get_args()` cannot silently produce a complete result. A first-class callable is
identified as creation, not executed as its named function. Literal `compact()` names
remain selectable as `possible-implicit-read` evidence; builtin binding and effects are
still unknown, including when a user function shadows that name.

An unknown transfer retains prior definitions and source sites as possible inputs, adds
possible unknown writes, and marks its continuation unknown. This intentionally
overshoots effects rather than certifying an incomplete dependency list. Goto/labels
and by-reference parameters require a whole-callable unknown region because their
influence cannot safely be isolated at their textual position.

| Edge in `uses` direction | Meaning |
|---|---|
| `reaching-definition` | A read receives a known or explicitly unknown definition. |
| `data` | A checked expression uses this local input. |
| `control` | A modeled predicate outcome guards this occurrence. `unknown-continuation` is unresolved. |
| `alternative` | One of the represented continuing paths. |
| `possible-input` | Evidence retained across an unknown transfer; not a proved dependency. |
| `unresolved-region` | A source occurrence belongs to an unsolved region. |
| `unknown-effect` | A possible write by that region. |

`used-by` reverses these edges and keeps their meanings. It is not a different solver.

## Completeness and diagnostics

`analysis` contains `status`, `complete`, per-selected-node states, structured `issues`
and a depth-limit `frontier`. States distinguish:

* `resolved`: the admitted local-origin rules closed the selected dependencies;
* `input`: origins are known runtime inputs; their values are deliberately unknown;
* `partial`: known evidence coexists with unresolved dependencies;
* `unknown`: a directly unresolved boundary;
* `not-analyzed`: no analysis is available;
* `truncated`: the requested traversal depth leaves edges unexplored.

The top-level completeness check is deliberately **callable-wide**, plus the selected
walk's depth limit. An issue elsewhere in the callable prevents a complete claim even
if an individual selected node is resolved. This conservative contract also protects
reverse queries whose omitted dependency could otherwise make an empty answer look
conclusive. `structure` is never truncated by `--level`.

Each issue includes a stable code, node ID, rule version, reason, source location/text
and affected analysis dimensions. Text warnings are generated from these same records.
`provenance` includes engine/rule/schema versions, runtime and target PHP, source SHA-256
and effective selection/filter options. Source IDs are opaque and are not stable across
edits; use the explicit location fields.

## Reporting an unsupported or incorrect result

`peq experimental issue result.json` prepares an issue for `k-kinzal/peq` **locally**.
It accepts both incomplete results and apparently resolved but incorrect results.
`--description` supplies expected/observed behavior. By default the report includes
metadata and diagnostic positions, but excludes source snippets and the graph. Paths,
symbol names and diagnostic identifiers may still reveal project information.

`--include-source` includes the saved result, including source snippets. The command
never reads additional files named by the artifact. It previews the exact repository,
title and body, then asks **`Send this issue? [y/N]`**. Only an interactive `y` or `yes`
sends, using the user's authenticated `gh` CLI. Empty input, No and noninteractive
execution send nothing. There is no auto-confirm option. Tests use mocks and an offline
subprocess, never a real GitHub issue.

Artifacts above 2 MB or issue bodies above 60 KB are rejected locally with an explanation;
no data is silently dropped to meet a publishing limit.

## Validation

Unit regressions cover checked origins, nested surviving guards, unknown propagation in
both directions, source preservation, depth limits and reporting consent. The project
and pinned [WordPress corpus](wordpress-variable-validation.md) are checked separately.
Structural consistency is not a semantic correctness proof: previously accepted examples
with unmodeled constructs now explicitly remain incomplete.
