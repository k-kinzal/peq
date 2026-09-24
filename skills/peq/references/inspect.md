# Inspect

`peq <symbol> [path]` walks the graph from one symbol and writes what it reaches.

This is the default command: `peq 'App\Domain\Invoice' src` and
`peq inspect 'App\Domain\Invoice' src` are the same. Use this when the question is
"starting here, what else is involved?" Use `peq graph` for detailed relation predicates, joins or call-site evidence,
including queries rooted at one symbol.

## Invocation

```bash
peq 'Namespace\Class' src --type=native --output=json --exclude vendor
peq 'Namespace\Class::method' src --type=native --output=json --exclude vendor -R
peq 'Namespace\functionName' src --type=native --output=json --exclude vendor -L 3
```

`path` defaults to the current working directory. Quote the target with `'`.

An agent is a program: `--type=native`, `--output=json`, and `--exclude vendor`
unless the question is about vendor.

## Target

One fully-qualified name:

| Kind | Form | Example |
|------|------|---------|
| Class, interface, trait, enum | `Namespace\Name` | `App\Domain\Invoice` |
| Method, property, constant, enum case | `Namespace\Name::member` | `App\Domain\Invoice::total` |
| Function | `Namespace\functionName` | `App\Support\money` |

## Options

| Option | What it does |
|--------|----------------|
| `path` | Directory to analyse. Default: current working directory. |
| `--filter=all\|calls\|depend` | Methods/functions default to calls; class-like targets default to depend. `all` keeps every relation. |
| `--config` | YAML file. Default: `<cwd>/.peq.yaml`. |
| `-D, --direction=uses\|used-by` | Which way the walk reads. Default: `uses`. |
| `-R, --reverse` | Shortcut for `--direction=used-by`. |
| `-L, --level` | Deepest depth to report. Omit for the whole reachable set. |
| `-O, --output` | `tree` (default) \| `json` \| `dot` \| `table` \| `graph` \| `mermaid`. |
| `-I, --include` / `-E, --exclude` | Path patterns. Repeatable. `--exclude` replaces the whole list, it does not add. |
| `--php-version` | PHP version the sources are written for. Default: the version peq runs on. Quote it in YAML (`phpVersion: '7.4'`). |
| `--type` | `native` (faster, PHAR) \| `phpstan` (when installed) \| `debug`. Prefer `native`. |
| `--memory-limit` | Process memory, e.g. `1G`. |
| `--debug-depth` / `--debug-seed` | Synthetic `debug` analyser only. Not for real sources. |

Later layers override earlier ones: defaults, then `PEQ_*`, then `.peq.yaml`, then flags.

### Filter

`calls` follows explicit calls and possible implementation bodies, without property,
signature or membership dependencies. A class target starts at its methods.
`depend` groups endpoints by class; a method target includes only dependencies of
that callable and the callables reached in the chosen direction. `all` restores the
unfiltered walk. Filtering does not change the analyzed graph.

Tree and table mark inferred implementation branches `(possible)`. Forward JSON
and diagram relations use `possible-call`; these are hierarchy candidates, not
runtime DI bindings. Use GQL for `receiverType`, `declaredTarget`, `implementationType`
and source positions, or to include only written calls.

### Direction

| Flag | Walks | Question it answers |
|------|-------|---------------------|
| default (`--direction=uses`) | away from the target | What does this depend on? |
| `--direction=used-by` or `-R` | towards the target | What depends on this? What does a change break? |

Impact analysis of a change is `-R`. A method's callees are the default.

### Depth

`-L 1` is the target's immediate relations only. The walk still records why a
branch stops; it does not invent nodes past the bound.

### Output

The walk itself does not change with `--output`.

| `--output` | Reader | What it writes |
|------------|--------|----------------|
| `tree` (default) | person | Indented tree. `(*)` means expanded elsewhere; `(recursive)` means a cycle. |
| `json` | program / agent | One object: `direction`, `symbol`, `nodes`. |
| `table` | review | One row per symbol: depth, symbol, kind, `file:line`. |
| `graph` | person | Every symbol once, joined by arrows, as wide as the terminal. |
| `mermaid` | a page | The same graph as a Mermaid flowchart. |
| `dot` | a renderer | The same graph as a Graphviz digraph. Pipe to `dot -Tsvg`. |

## JSON document

```json
{
    "direction": "uses",
    "symbol": "App\\Domain\\Invoice",
    "nodes": [
        {
            "id": "App\\Domain\\Invoice",
            "kind": "class",
            "resolved": true,
            "depth": 0,
            "parent": null,
            "relations": [],
            "truncated": null,
            "file": {"path": "/project/src/Domain/Invoice.php", "line": 12, "column": 1}
        }
    ]
}
```

`nodes` is the walk in visit order, not a unique set. The same `id` can appear twice
when it is reached by two parents; `truncated` then tells the reader the second
visit was not expanded.

| `truncated` | Meaning |
|-------------|---------|
| `null` | Expanded here, or a genuine leaf (nothing below it in this direction). |
| `"repeated"` | Expanded on another branch. Tree writes `(*)`. |
| `"recursive"` | Already open on the path above. Tree writes `(recursive)`. |

`kind` is the graph's own vocabulary: `class`, `interface`, `trait`, `enum`,
`method`, `function`, `property`, `constant`, `enum_case`, `builtin`, `unknown`.

`relations` names the edges that led here from `parent`, as the analyser spells
them (`method-call`, `declaration-method`, `declaration-extends`, …). Inverse
walks (`used-by`) use `used-by` and `declared-in`.

`file` is `null` when analysis only ever saw the name referred to, not declared.

Empty stdout and a non-zero exit mean the symbol was not in the graph. See
[troubleshooting.md](troubleshooting.md).

## Typical questions

**Blast radius of a change** — who would notice if this method moved or broke:

```bash
peq 'App\Domain\Money::add' src -R --type=native --output=json --exclude vendor
```

**What this class is built on**, bounded so the document stays small:

```bash
peq 'App\Domain\Invoice' src -L 3 --type=native --output=json --exclude vendor
```

**A picture for a note:**

```bash
peq 'App\Domain\Invoice' src --type=native --output=mermaid --exclude vendor
```
