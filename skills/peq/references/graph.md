# Graph

`peq graph '<query>' [path]` runs a GQL query against the dependency graph and
writes a table.

Use this when the question is not "walk out from this one symbol". Counts, filters,
joins, paths, "every public method that reaches the cache" — those are queries.
Inspect remains the right tool for a single blast radius drawn as a tree.

The language is ISO/IEC 39075 GQL. peq does not extend it. A clause, operator, or
function the standard does not define is refused. Read-only: nothing in the language
can change the graph.

## Invocation

```bash
peq graph --schema --output=json
peq graph 'MATCH (m:Method) RETURN m.id LIMIT 5' src --type=native --output=json --exclude vendor
```

Quote the query with `'`. An agent is a program: `--type=native`, `--output=json`,
and `--exclude vendor` unless the question is about vendor.

`--schema` writes the vocabulary instead of running a query. It does not analyse
sources. Ask for it in JSON the first time a session needs a name. The table has
columns `category`, `name`, `type`. `name` is already spelled the way a query has
to write it, back quotes included. Paste from it; do not strip them.

## Options

| Option | What it does |
|--------|----------------|
| `query` | The GQL to run. Omit when `--schema` is set. |
| `path` | Directory to analyse. Default: current working directory. Unused by `--schema`. |
| `--schema` | Write labels, properties, functions and aggregates, instead of running a query. |
| `--config` | YAML file. Default: `<cwd>/.peq.yaml`. |
| `-O, --output` | `table` (default) \| `json` \| `tree` \| `graph` \| `mermaid` \| `dot`. |
| `--hops` | Largest upper bound a `{m,n}` quantifier may write. Default: `10`. |
| `-I, --include` / `-E, --exclude` | Path patterns. Repeatable. `--exclude` replaces the whole list, it does not add. |
| `--php-version` | PHP version the sources are written for. Default: the version peq runs on. Quote it in YAML (`phpVersion: '7.4'`). |
| `--type` | `native` (faster, PHAR) \| `phpstan` (when installed) \| `debug`. Prefer `native`. |
| `--memory-limit` | Process memory, e.g. `1G`. |
| `--debug-depth` / `--debug-seed` | Synthetic `debug` analyser only. Not for real sources. |

Later layers override earlier ones: defaults, then `.peq.yaml`, then `PEQ_*`, then
flags. Inspect's `--direction` does not apply; the arrow in the pattern is the
direction.

## Shell and reserved names

Six of peq's names spell words GQL reserves. A `"` shell argument would take the
back quotes for command substitution.

| Name | Kind | Written in a query as |
|------|------|------------------------|
| `Function` | node label | `` :`Function` `` |
| `Unknown` | node label | `` :`Unknown` `` |
| `call` | edge family | `` -[:`call`]-> `` |
| `value` | node property | `` n.`value` `` |
| `parameters` | node property | `` n.`parameters` `` |
| `abstract` | node property | `` n.`abstract` `` |

`--schema` already emits these with the quotes. `type` is not reserved. Functions
such as `count(` and `left(` are reserved words too, but a call is written with a
parenthesis and does not need quoting. `usedBy` and `declaredIn` are not query
labels: a pattern reads an edge backwards by pointing the arrow the other way.

## Output

| `--output` | What it writes |
|------------|----------------|
| `table` (default) | Headed columns, one row per binding. |
| `json` | `{status, condition, columns, rows}`. Status first. |
| `tree` | The paths the query bound, as a tree. |
| `graph` / `mermaid` / `dot` | The subgraph the answer holds, drawn. |

### JSON document

```json
{
    "status": "00000",
    "condition": "note: successful completion",
    "columns": [{"name": "n", "type": "INT64"}],
    "rows": [[17]]
}
```

`rows` is an array of arrays, in column order. A `NODE` cell is
`{"id","labels","properties"}`. An `EDGE` cell adds `origin` and `target`. A `PATH`
cell is the chain of both, in walk order.

Read `status` before the rows. `00000` produced rows; `02000` produced none and is
still success. Any other code is a failure — see
[troubleshooting.md](troubleshooting.md).

## Labels

A symbol carries a specific kind, a family, and whether analysis found it. Families
keep a query from enumerating kinds it would then have to keep up to date.

### Node labels

| Specific | Families | Also |
|----------|----------|------|
| `Class` `Interface` `Trait` `Enum` | `ClassLike` | `Resolved` or `Unresolved` |
| `Method` | `Member`, `Callable` | |
| `` `Function` `` | `Callable` | |
| `Property` `Constant` `EnumCase` | `Member` | |
| `Builtin` | | |
| `` `Unknown` `` | | usually `Unresolved` |

`(c:ClassLike&!Interface)` is every class-like that is not an interface.
`(:Callable)` is methods and functions together.

### Edge labels

A pattern names a relation the way a sentence would, not the way the analyser
stores it. `method-call` in inspect JSON is `methodCall` here.

| Family | Members |
|--------|---------|
| `` `call` `` | `functionCall`, `methodCall`, `staticCall` |
| `usage` | every call, plus `instantiation`, `propertyAccess`, `staticPropertyAccess`, `constFetch`, `instanceOf`, `catches` |
| `declares` | `declaresMethod`, `declaresProperty`, `declaresConstant`, `declaresEnumCase` |
| `signatureType` | `parameterType`, `returnType` |
| `declaration` | `declares*` and `signatureType`, plus `traitUse`, `extends`, `implements`, `propertyType`, `attribute` |

`` -[:`call`]-> `` is "reached by calling". `-[:declaration]->` is everything a
class-like writes down about itself.

```
(a)-[:methodCall]->(b)    a calls b
(a)<-[:methodCall]-(b)    b calls a
(a)-[:methodCall]-(b)     either way
```

## Properties

A property the source does not speak to is absent, not nulled with a stand-in.
`p.visibility IS NULL` means "this kind of symbol has no visibility", not
"package-private".

### Node properties

Always present: `id`, `kind`, `name`, `namespace`, `resolved`.

When analysis knows where it is written: `file`, `fileName`, `line`, `column`.

When it is a member: `owner`.

When a declaration was read: `visibility`, `static`, `` `abstract` ``, `final`,
`readonly`, `deprecated`, `attributes`, and, when they apply, `type`,
`` `value` ``, `signature`, `returnType`, `` `parameters` ``, `parameterTypes`,
`parameterCount`.

`kind` is the analyser's spelling (`class`, `method`, …). `visibility` is
`"public"`, `"protected"`, or `"private"`. `id` is the same string inspect takes
as a target (`App\Domain\Invoice::total`).

### Edge properties

`kind`, `file`, `fileName`, `line`, `column`. The line of a call is on the edge,
not on either symbol — a query that ends by saying where to look returns `e.line`.

## Functions and aggregates

Functions: `char_length`, `upper`, `lower`, `trim`, `left`, `right`, `size`,
`elements`, `path_length`, `coalesce`, `nullif`, `zoned_datetime`.

Aggregates: `count`, `sum`, `avg`, `min`, `max`, `collect_list`. Names match
without regard to case. `count(*)` summarises rows; `RETURN count(m)` without
`GROUP BY` still produces one row.

`--schema` is authoritative. A name that merely appears in GQL's grammar is not
therefore a function: `labels(x)` is not GQL here.

## Hops

`--hops` (default 10) is the largest upper bound a quantifier such as `{1,6}` may
be written with. A query that writes `{1,20}` is refused before it runs, unless
`--hops` is raised. A quantifier with no upper bound (`*`, `+`) is not limited by
this; GQL allows one only under a restrictor that keeps matches finite.

Unbounded `*` without a restrictor is a syntax error, not a slow query.

## Recipes

**Public methods per class:**

```bash
peq graph 'MATCH (c:Class)-[:declaresMethod]->(m:Method WHERE m.visibility = "public")
           RETURN c.name AS class, count(m) AS methods
           GROUP BY class ORDER BY methods DESC LIMIT 10' src --type=native --output=json --exclude vendor
```

**Callers of one method (blast radius as a table):**

```bash
peq graph 'MATCH (m:Method WHERE m.id = "App\Domain\Money::add")<-[e:`call`]-(c:Callable)
           RETURN c.id, e.file, e.line' src --type=native --output=json --exclude vendor
```

**What a method reaches by calling, up to three hops:**

```bash
peq graph 'MATCH (m:Method WHERE m.id = "App\Http\CheckoutController::pay")-[:`call`]->{1,3}(t:Callable)
           RETURN DISTINCT t.id' src --type=native --output=json --exclude vendor
```

**Implementations of an interface:**

```bash
peq graph 'MATCH (c:Class)-[e:implements]->(i:Interface WHERE i.name = "Clock")
           RETURN c.id, e.file, e.line' src --type=native --output=json --exclude vendor
```

**Deprecated symbols still used:**

```bash
peq graph 'MATCH (n WHERE n.deprecated = TRUE)<-[e:usage]-(u)
           RETURN n.id, u.id, e.file, e.line' src --type=native --output=json --exclude vendor
```

**Unresolved names (analysis saw a reference it could not bind):**

```bash
peq graph 'MATCH (n:Unresolved)
           RETURN n.id, n.kind
           ORDER BY n.kind, n.id' src --type=native --output=json --exclude vendor
```
