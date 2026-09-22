# WordPress source and dependency validation

The structure-first redesign is validated against the official
[WordPress 7.1.1 archive](https://wordpress.org/wordpress-7.1.1.tar.gz), including core,
bundled themes and libraries. Sources are parsed as PHP 7.4 on PHP 8.5.8. The corpus
checks do not install WordPress, use a database, or execute application code.

Archive identity:

* SHA-1: `2a9d68474e8703aa3a66f80427b325e0941b6a1e`, matching the
  [published checksum](https://wordpress.org/wordpress-7.1.1.tar.gz.sha1).
* SHA-256: `3996fee13448ef12e07e9f0c77db2f655ffa1b7cde71c80a4965d3bf1fb956b3`.
* The seven files used for source comparisons are also pinned by SHA-256 in the tests.

## What changed in the validation contract

The earlier implementation produced graphs for 9,912 callables and refused 2,029.
Its 40 manually checked reaching-definition examples passed, but further investigation
found omitted loop-exit conditions and implicit local reads. Those results did **not**
establish semantic completeness, and their passing snapshots are not carried forward
as a completeness claim.

The current contract preserves every source site and admits only checked transfer rules.
All 11,941 named declarations in 1,512 parsed PHP files now retain a source inventory and
graph, including previously refused constructs. Unknown regions are expected results,
not successful resolutions. The corpus checks dangling edges, cross-variable definitions,
self dependencies and equality between AST node count and retained syntax-site count.

The separate source comparison suite has 60 cases: seven file hashes, 40 original
selection locations, four reverse selections, three condition selections, one implicit
read path, three storage boundaries, and two additional positive/negative checks.
The former loop/reference/call expectations now require explicit uncertainty and
preservation of the audited source sites; they do not certify those transfers.

## Counterexamples checked against source

| Source or minimal case | Current result |
|---|---|
| `isset($a, $b[$i = 1])` | `UNSUPPORTED_EXPRESSION`; the conditional write cannot become the sole certified definition. |
| `while ($flag) { return 0; } return 1;` | `UNSUPPORTED_STATEMENT`; the final return retains an unknown loop continuation. |
| Nested `if ($a) { if ($b) { return 0; } } return 1;` | The surviving `($a && !$b) || !$a` condition is explicitly represented. |
| `get_defined_vars()` / `func_get_args()` | `OPAQUE_CALL`, retaining local inputs rather than asserting no dependency. |
| `assert(++$i)` | `OPAQUE_CALL`; assertion configuration does not silently make the increment unconditional. |
| Repeated tests of `$flag` and `!$flag` | `PATH_CORRELATION`; feasibility is not certified. |
| Testing a value chosen by an earlier branch, including a copied value | `PATH_CORRELATION`; definition guards also carry origin information. |
| `extract(...)` / `compact(...)` first-class callable creation | `CALLABLE_CREATION`, not variable injection or an executed compact call. |
| String conversion, output conversion and object destruction | `OPERAND_TYPES`, `OUTPUT_CONVERSION` or `VALUE_LIFETIME` unless scalar preconditions are proved. |
| `false ?? ($i = 1)` | The fallback is skipped; false and null are distinguished. |
| WordPress `_wp_check_alternate_file_names`, `functions.php:2857` | The final false return has an unknown dependency on the foreach at 2847. Reverse inspection of `$filenames` includes the final return. |
| peq `SymbolDeclaration::hasAttribute`, `SymbolDeclaration.php:84` | The loop at 78 remains an unresolved guard; reverse inspection of `$name` includes the false return. |
| WordPress `__return_false`, `functions.php:7103` | Resolved literal plus return, with no invented inputs. |

The WordPress loop example contains two early true returns, at 2849 and 2853. Reaching
2857 depends on not taking either during iteration. The old two-node literal/return
answer omitted this condition. The new result explicitly retains the unresolved region
and is rejected by `--strict` with exit 2.

Literal compact names remain selectable, for example `$ext` at `wp_check_filetype:3096`.
They are `possible-implicit-read` evidence across an opaque call. This also avoids
claiming builtin behavior when a written function shadows the name. Heap state,
reference effects, hook execution and SQL/database behavior are not resolved.

## Reproduce

Keep the archive in ignored `var/cache/`; no WordPress sources are redistributed. The
external tests are intentionally outside the default PHPUnit suites.

```bash
mkdir -p var/cache/wordpress-validation/7.1.1
curl --fail --location https://wordpress.org/wordpress-7.1.1.tar.gz \
  --output var/cache/wordpress-validation/wordpress-7.1.1.tar.gz
printf '%s  %s\n' \
  3996fee13448ef12e07e9f0c77db2f655ffa1b7cde71c80a4965d3bf1fb956b3 \
  var/cache/wordpress-validation/wordpress-7.1.1.tar.gz | shasum -a 256 --check
# Continue only after the checksum reports OK.
tar -xzf var/cache/wordpress-validation/wordpress-7.1.1.tar.gz \
  -C var/cache/wordpress-validation/7.1.1
php -d xdebug.mode=off vendor/bin/phpunit tests/External

bin/console experimental inspect _wp_check_alternate_file_names \
  var/cache/wordpress-validation/7.1.1/wordpress/wp-includes/functions.php \
  --line 2857 --strict --output=json --php-version=7.4
# Expected: JSON analysis.complete=false and exit 2.

bin/console experimental inspect wp_check_filetype \
  var/cache/wordpress-validation/7.1.1/wordpress/wp-includes/functions.php \
  --line 3096 --variable ext --output=json --php-version=7.4
```

These checks establish preserved evidence and explicit incompleteness for the reviewed
cases. They do not establish complete PHP execution, path feasibility or an overall
semantic accuracy percentage. The unresolved surface is deliberately larger than in
the earlier optimistic experiment.
