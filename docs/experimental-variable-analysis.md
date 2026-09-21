# Experimental variable dependencies

`peq experimental inspect` answers two questions inside one written method or function:
which source occurrences can determine a variable here, and which later occurrences can
be affected by this occurrence. It is an explicit opt-in experiment. Its output schema
and analysis behavior may change independently of the production symbol graph.

```bash
# Where does $matched on the return line come from?
peq experimental inspect 'App\Gql\Lexing\SourceCursor::capture' src \
  --line 111 --variable matched --output=json

# Which occurrences can a change on this line affect?
peq experimental inspect 'App\Config\ConfigLoader::load' src \
  --line 41 --direction=used-by --output=mermaid
```

`--line` is a required absolute source-file line, starting at one. `--variable` accepts
`name` or `'$name'`; omitting it selects all recorded occurrences starting on that line.
If a variable occurs more than once, all its occurrences are selected. `--column` narrows
that selection to a 1-based byte column. A compound assignment reads and writes at the
same position, so it selects both roles. A line with no occurrence, an unknown callable,
or unsupported execution semantics produces an error and a nonzero exit status.

The target is a fully qualified declared method or function. Select the trait's own
method when inspecting a trait body. Inherited methods, anonymous callables as targets,
and callers/callees outside this scope are not resolved by this command.

All six formats are available: `tree`, `json`, `table`, `dot`, `graph`, and `mermaid`.
They receive the same graph slice, including the same cycles and dependency kinds.
`--level` bounds the number of edges from the selected occurrences. `--reverse` is an
alias for `--direction=used-by`. Configuration, file filters, memory limits and
`--php-version` work as in standard inspection. The experimental command always uses
`ExperimentAnalyzer`, a copy of the native source pipeline; it does not accept `--type`.
The copied symbol pipeline is checked against native on this project's complete `src`.

## Reading an answer

A node identifies a **source span and role**, not just a variable name. Parameters,
reads, writes, unset values, expression values and returns are separate occurrences.
The source file, start line, byte column, end line and source text accompany every JSON
node. IDs are opaque; consumers should read these fields instead of parsing an ID.

For example, in `$a = $input; $copy = $a; $a = 9; return $copy;`, the return depends on
the first assignment to `$a`. The later overwrite has no edge to `$copy`.

| Edge | Meaning in `uses` direction |
|------|-----------------------------|
| `reaching-definition` | A variable read can receive the value of this definition. |
| `data` | This local value is computed using this expression or source occurrence. |
| `control` | Execution or selection depends on this predicate; `branch` states its outcome. |
| `call-input` | An argument or receiver is supplied to an opaque call. This does not prove its return value depends on that input. |
| `boundary-input` | An address or receiver is used by an opaque property access. This does not describe the stored property value. |

`used-by` reverses the arrows while preserving their kind and branch. Multiple reaching
definitions are alternatives, not a claim that every definition executes. The analysis
joins possible paths and does not solve constraints or correlate separate predicates.
Only syntactically constant truth conditions are pruned.

Assignments kill earlier whole-variable definitions. Branches, early return/throw,
short-circuit operators, ternaries, match, switch fallthrough, break/continue depths,
and `for`/`foreach`/`while`/`do` loops preserve their local control flow. Loops converge to
a reaching-definition fixed point, rather than being unrolled a chosen number of times.
Array elements are tracked as one aggregate; element dependencies are possible and are
reported with a diagnostic. Unset and potentially unbound reads remain visible.

## Boundaries

Calls, heap accesses and nested callable bodies are explicit boundaries, reported in
`diagnostics` in JSON and as text/comments in the other formats. Boundary inputs explain
what was supplied, not the callee's implementation or a proven returned value. Receiver
type inference, heap alias analysis, interprocedural return flow, exceptions raised by
calls, and feasibility of paths are outside this experiment.

Known reference parameters create `call-write` definitions. Signatures come from the
analyzed declarations for named functions, explicit static calls, constructors and
`$this` calls, or from installed PHP builtins. Signatures of unresolved calls and effects
of argument unpacking are not inferred. Builtin output-only parameters of `preg_match`,
`preg_match_all`, `parse_str`, and `mb_parse_str` do not read a plain output variable's
previous value. Other known reference parameters are treated as opaque input/output
boundaries. Builtin signatures reflect the runtime used to run peq.

`compact('name')` also reads the named local variable. Literal names and nested unkeyed
literal lists are supported; runtime names, keyed lists and unpacking are rejected.
These reads have kind `implicit-read`, with the string literal's source span, and can
be selected with `--variable name`. Their reaching definitions include possibly unbound
or unset values, which PHP can omit from the resulting array. The call remains an
explicit boundary; the analyzer does not evaluate the returned array.

Closure captures are read at closure creation; their bodies do not execute in the outer
scope. Lexical parameters in nested arrows do not become outer variable dependencies.
Explicit reference aliases/captures, reference iteration, shared global/static storage,
dynamic variable names, eval/include, generators, goto, nullsafe access, try/catch/finally,
and calls that inject local variables are rejected instead of producing a misleading
partial graph. These restrictions apply to the selected callable, not unrelated methods.

## Validation

`tests/Unit/Analyzer/ExperimentAnalyzer` includes the copied native tests and a corpus of
literal expected reaching definitions. Regression cases cover overwrites, missing branch
assignments, abrupt exits, loop-carried definitions, switch fallthrough, closures,
reference outputs, nested expression spans and read/write occurrence selection.

`tests/Integration/ExperimentalSelfAnalysisTest.php` checks the complete project for
dangling edges, definitions belonging to another variable, and direct self dependencies.
It also keeps manually audited expectations for `ConfigLoader::load`,
`SourceCursor::capture`, `PhpVersion::parse`, and `RowPlacement::fit`. These checks are
regressions for the stated local semantics, not a claim of complete PHP data-flow analysis.

The [WordPress 7.1.1 validation](wordpress-variable-validation.md) records a separate
external corpus audit, manually checked source-to-graph expectations in both directions,
and a `compact()` dependency omission found and fixed by that exercise. Its pinned
download and external tests can be rerun without adding WordPress to this repository.
