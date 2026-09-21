# Troubleshooting

peq reports a failure as one sentence on stderr and a non-zero exit. A query that
found nothing is not a failure: `02000` exits zero. Read the five-character
`GQLSTATUS` of a query before rewriting it.

## The command is missing

`peq: command not found` means the binary is not on `PATH`.

Install the PHAR from [GitHub Releases](https://github.com/k-kinzal/peq/releases)
and put it on `PATH` as `peq`, or `composer require --dev k-kinzal/peq` and call
the Composer bin.

peq itself needs PHP 8.3 or newer to run. That is separate from `--php-version`,
which is the version the *sources* are written for.

## The symbol is not in the graph

Inspect writes:

```
Symbol "App\Domain\Invoice::total" is not in the dependency graph, which was read as PHP 8.5.
Check the spelling, the analyzed path, the include and exclude patterns, and the PHP
version the sources are written for.
```

Stdout is empty. Check, in that order:

1. **Spelling.** Fully qualified, `::` for a member, quoted with `'` so the shell
   keeps `\`. `Invoice` is not `App\Domain\Invoice`.
2. **Path.** Point `path` at the directory that actually holds the sources. A
   repository root that includes nothing matching `--include`, or excludes them,
   produces an empty graph.
3. **Include / exclude.** `--exclude vendor` is right for application code;
   `--exclude src` is not. `--exclude` replaces the whole list rather than
   appending, so a `.peq.yaml` exclude list is discarded when the flag is passed.
4. **PHP version.** A file the chosen version cannot parse is omitted, so the
   symbol is absent rather than merely unreachable. `match` is a method name in
   PHP 7 and a keyword in PHP 8; `$text{0}` is a string offset up to 7.4. Pass
   `--php-version` matching what the sources are written for. In YAML, quote it:
   unquoted `8.10` is the number `8.1`.

`--type=debug` builds a synthetic graph and will never hold a real symbol.

## The analyser cannot run

| Message | What to do |
|---------|------------|
| `Invalid configuration "type": expected one of native, …, got "phpstan"` | This build does not carry PHPStan (the PHAR never does). Use `--type=native`. |
| `This build of peq does not carry PHPStan, so the analyzer built on it cannot run.` | Same: `--type=native`. |
| `Invalid configuration "phpVersion": the phpstan analyzer reads sources written for PHP 7.1 and newer` | Native reads 5.6+. Switch `--type=native`, or raise `--php-version`. |
| `Invalid configuration "phpVersion": expected a PHP version between 5.6 and 8.5` | The value is not a PHP peq analyses. |
| `Invalid configuration "output"` / `"direction"` / `"type"` | The value is not one of the closed set. See the Options table of the command you ran. |
| `Failed to parse YAML configuration file` | `.peq.yaml` is not YAML. Pass `--config` to a real file, or fix it. |
| `Allowed memory size of … exhausted` | `--type=native`, `--exclude vendor`, point `path` at `src`, and/or `--memory-limit=1G`. PHPStan analysis of a large tree is the usual cause. |

An analysis that finishes but takes too long is the same shape: `--type=native`,
narrow `path`, `--exclude vendor`. `-L` only bounds the report, not the analysis.

## The query failed

A failed query writes `[<code>] <condition>: <reason>` to stderr, with line and
column when the fault has a place. JSON puts the same code in `status`.

| `GQLSTATUS` | Meaning | What to do |
|-------------|---------|------------|
| `00000` | Produced at least one row | Use the rows. |
| `02000` | Produced no rows | That is the answer. Do not retry the same query. |
| `42001` | Could not be read as GQL | Fix syntax. See below. |
| `42000` | Named a function, aggregate, or statement peq does not offer | Drop it. Ask `peq graph --schema --output=json`. |
| `42002` | Named something not bound here | The variable or property is not in scope. |
| `22G03` / `22G04` / `22003` / `22012` / `22011` | Data exception | The query parsed; an operation could not be applied to these values. |

`INSERT`, `CREATE`, `SET`, `DELETE` and the other statements that would change a
graph are GQL, and peq refuses them under `42000` by name: it answers questions
about source code, it does not keep a graph.

### `42001` — the query did not parse

Usual causes, in the order they actually happen:

1. **The shell ate the back quotes.** The query was wrapped in `"` and `` `call` ``
   became command substitution. Wrap the whole query in `'`.
2. **A reserved name was written plainly.** `` :Function ``, `` -[:call]-> ``,
   `` n.value ``, `` n.parameters ``, `` n.abstract ``, `` :Unknown `` each need
   back quotes. Paste the `name` column of `--schema`; do not strip them.
3. **`*` / `+` / `{1,}` without a restrictor.** A walk may revisit what it has
   crossed, so an unbounded repetition is infinite unless the path says `TRAIL`,
   `SIMPLE`, or `ACYCLIC`. Write `{1,3}` instead, or put a restrictor before the
   path.
4. **`--hops` is too small.** `{1,20}` is refused when `--hops` is 10:
   `a quantifier may be written with an upper bound of at most 10 here…: raise --hops to allow more`.
5. **A character GQL does not write**, or a string / quoted name that is never
   closed. The message names the line and column.

`labels(x)` is not a function here. `--schema` is the list of functions; a word
that appears in the grammar is not therefore callable.

### `02000` — nothing matched

The query ran. Typical reasons it bound no rows:

- The `id` or `name` in `WHERE` does not match what the graph holds. Inspect the
  symbol, or `RETURN n.id LIMIT 20` with a looser pattern.
- The arrow is the wrong way. Callers of `m` are `` <-[:`call`]- ``, not `` -[:`call`]-> ``.
- `--exclude` or `path` left the relevant sources out, so the graph never held
  them. Same checks as a missing inspect symbol.
- `--php-version` dropped files the chosen version cannot parse.

Do not rewrite a correct query that found nothing. The absence is the answer.

## The result is the wrong shape

- **Inspect JSON is huge.** Bound it with `-L`, or ask graph a narrower question.
- **A call's line is missing.** It lives on the edge, not on either symbol. Bind
  the edge: `` -[e:`call`]-> `` and return `e.line`.
- **A property is JSON `null` / SQL-looking NULL.** The source did not declare it.
  `visibility IS NULL` means "this kind of symbol has no visibility".
- **`truncated: "repeated"`.** The symbol was expanded on another branch. That is
  the walk, not a missing edge.
- **Flags seem ignored.** Defaults, then `.peq.yaml`, then `PEQ_*`, then the
  command line. `PEQ_OUTPUT=tree` will override a YAML `output: json` unless the
  flag is passed.
