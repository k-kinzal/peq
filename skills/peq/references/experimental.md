# Experimental

Use `peq experimental inspect` to trace a variable's source origins, affected
expressions, and control conditions. It reads PHP without executing it. This is a
separate occurrence graph: ordinary `peq inspect` and `peq graph` still answer
symbol-level questions such as callers and callees.

For "under which condition is this method called?", first locate the call sites
with the symbol graph, then inspect the relevant line in each enclosing callable.
Experimental inspection does not expand the called method's body.

## Invocation

```bash
peq experimental inspect 'SqlCatalog\Analyzer:analyzePaths$sources' src --direction=uses --output=json --exclude vendor --strict
peq experimental inspect 'SqlCatalog\Analyzer:analyzePaths$sources' src --direction=used-by --output=json --exclude vendor --strict
peq experimental inspect 'SqlCatalog\Analyzer:$sources' src --direction=uses --output=json --exclude vendor --strict
peq experimental inspect 'App\Support\normalize$path' src --direction=uses --output=json --exclude vendor --strict
```

Use fully qualified names and single quotes: the shell otherwise expands `$`.
`path` accepts a file or directory and defaults to the working directory. Prefer
JSON for an agent and set the direction explicitly; configuration can override
the baseline `uses`. There is no `--type=experimental`: this command selects the
experimental analyzer itself. If unavailable, check `peq experimental --help`
and report the installed build's limitation.

## Selecting an occurrence

| Target | Selects |
|--------|---------|
| `Namespace\Class:method$variable` | A local variable or parameter in that written method. |
| `Namespace\function$variable` | A local variable or parameter in that written function. |
| `Namespace\Class:$property` | A declared property and its direct receiver sites in that class. |

Without `--line`, `used-by` starts at the first source occurrence, usually a
parameter or declaration; `uses` starts at the last. A final self-assignment
selects its completed write, so `$x = $x + 1` traces the resulting `$x`.
Source order does not establish runtime execution order, especially across
methods in a property report. A last occurrence in unsolved syntax remains
visible as not analyzed; it is not silently skipped.

`--line N` selects matching occurrences starting on that line. Add `--column N`
(1-based byte column) to distinguish occurrences on the same line. A callable
target without a variable selects the whole line:

```bash
peq experimental inspect 'SqlCatalog\Analyzer:analyzePaths$sources' src --line 42 --column 9 --direction=uses --output=json --strict
peq experimental inspect 'SqlCatalog\Analyzer::analyzePaths' src --line 42 --direction=uses --output=json --strict
```

The legacy `Class::method --variable sources` form also works. Omit `--line`
unless a particular occurrence matters; when inspecting a call site, use its line
and the enclosing callable rather than the callee's name.

`-L N` bounds the number of dependency edges from the roots, not analysis work.
`--output` accepts `tree|json|dot|table|graph|mermaid`; all use the same selection.
Path filters, `--config`, `--php-version` and `--memory-limit` work as described in
[inspect.md](inspect.md#options).

## Reading JSON and conditions

Read `experimental: true`, `schemaVersion: 2`, `semantics` and `analysis` before
making a dependency claim. This schema is different from ordinary inspect JSON.

| Field | How to use it |
|-------|---------------|
| `roots`, `nodes`, `edges` | Roots are occurrence IDs. Nodes carry `id`, `kind`, `variable`, `line`, `column`, `endLine`, `text`; edges carry `from`, `to`, `kind`, `branch`. |
| `analysis.complete` | Completeness covers the whole callable (the class for property inspection) and the selected traversal. An issue outside the displayed slice can make this false. |
| `analysis.nodes` | Status by selected node ID: `resolved`, `input`, `unknown`, `partial` or `not-analyzed`. `input` is an external parameter/receiver origin, not an analysis failure. |
| `analysis.issues` | Structured reasons: `code`, `nodeId`, `rule`, `reason`, `source` and `affects`. Preserve these when explaining missing information. |
| `analysis.frontier` | Occurrences left unexplored by the depth bound. Overall status becomes `truncated`. |
| `structure` | Lexical inventory, including unsolved syntax, with `source`, `syntax`, `parent`, `role`, source spans and statically written `target` names. Containment is not proof of execution. |
| `provenance` | Requested target, selection mode, coordinates, direction, analysis versions, source hash and configuration needed to reproduce the result. |

Edges are oriented in the requested traversal direction. In `uses`, `from`
depends on `to`; in `used-by`, that arrow is reversed to reach affected
occurrences. For a `control` edge, read the condition occurrence and its `branch`
(for example `truthy`, `falsy` or `iterate`), and cite its source line. Follow its
dependencies to explain the condition's origins. Branch labels do not prove
runtime values or that a combination of branches is feasible.

Local assignments, if/elseif/else, ternaries and short-circuit expressions have
checked rules. Loops are modeled too: `while`, `do-while`, `for` and `foreach`
retain initialization, tests, bodies, iteration bindings, updates and exits.
Nonrepeating loops can resolve; repeated definitions retain back edges and
`LOOP_RECURRENCE` when cross-iteration reasoning is unsolved. Iterator protocols
and reference iteration have separate Unknown reasons.

Calls, array construction/access, aliases, heap effects and unsupported flow such
as switch/match retain explicit boundaries. Replacing a value whose type is not
proven scalar can also be Unknown because of possible destructors or aliases.
Property inspection always retains `PROPERTY_LIFECYCLE`:
object identity, external writes and method invocation order are unresolved.
`possible-input` and `unresolved-region` edges are evidence across Unknown, not
proven dependencies. Missing edges across these boundaries do not establish
absence of an effect. Explain both the known origins/conditions and the issue
codes with locations; do not present an incomplete graph as an exhaustive blast
radius. Even `analysis.complete: true` does not prove runtime safety.

## Exit codes and saved results

With `--strict`, exit `0` means complete, `2` means incomplete or truncated with
the result still written, and `1` means an invalid request or configuration.
Without `--strict`, incomplete analysis also exits `0`; always read `analysis`.
Handle exit `2` as a usable partial report, rather than discarding stdout or
retrying the same command. For example, in Bash:

```bash
peq_exit=0
peq experimental inspect 'SqlCatalog\Analyzer:analyzePaths$sources' src --direction=uses --output=json --strict > result.json || peq_exit=$?
case "$peq_exit" in
  0|2) jq '{target, direction, roots, analysis, provenance}' result.json ;;
  *) cat result.json >&2; exit "$peq_exit" ;;
esac
```

## Reporting an analysis issue

Prepare a local preview from saved JSON, including resolved-but-wrong results:

```bash
peq experimental issue result.json --description 'Expected ...; observed ...' --no-interaction
```

`--no-interaction` previews the exact repository, title and body and sends
nothing. Source snippets are omitted by default; paths, symbol names and analysis
metadata remain. `--include-source` opts into snippets already in the saved JSON.
The input must be schema version 2 and smaller than 2 MB; the issue body is limited
to 60 KB.

Inspection and preview do not authorize publication. Show the draft to the user
before sending to `k-kinzal/peq`. The interactive command asks
`Send this issue? [y/N]`; only explicit `y` or `yes` sends, using authenticated
`gh`. Do not pipe an automatic Yes or bypass that confirmation. No, the default,
and noninteractive mode send nothing.
