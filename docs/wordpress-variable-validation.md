# WordPress variable dependency validation

Validation performed on September 22, 2026, against the official
[WordPress 7.1.1 release archive](https://wordpress.org/wordpress-7.1.1.tar.gz), including
the PHP files under `wp-admin`, `wp-includes`, and the bundled themes and libraries.
WordPress was parsed as PHP 7.4 using peq on PHP 8.5.8. No WordPress installation,
database, plugins, or application execution was involved.

Archive identity:

- SHA-1: `2a9d68474e8703aa3a66f80427b325e0941b6a1e`, checked against WordPress's
  [published checksum](https://wordpress.org/wordpress-7.1.1.tar.gz.sha1).
- SHA-256: `3996fee13448ef12e07e9f0c77db2f655ffa1b7cde71c80a4965d3bf1fb956b3`.
- The seven files used for manual expectations are also pinned by SHA-256 in the tests.

## Results

| Check | Result |
|---|---|
| PHP files collected / parsed | 1,512 / 1,512 |
| Named function and method declarations examined | 11,941 |
| Local dependency graphs produced | 9,912 |
| Explicit unsupported-semantics refusals | 2,029 |
| Unexpected exceptions / timeouts | 0 / 0 |
| Dangling edges / cross-variable definitions / direct self dependencies | 0 / 0 / 0 |
| Recorded source occurrences / edges | 547,109 / 2,405,988 |
| Manually specified reaching-definition checks through the CLI | 40 / 40 passed |
| Reverse direction / branch labels / return path / refusal CLI checks | 11 / 11 passed |

The complete corpus scan took approximately nine seconds after starting source indexing
and peaked at 1.43 GiB in this environment. This is a local observation, not a benchmark.
Written signatures were indexed across the complete archive before checking call effects.

The 40 exact reaching-definition expectations cover seven functions and five methods.
Expected source lines were obtained by reading their assignments and control flow before
comparing the CLI's JSON. The tests compare complete sets, so an extra definition also
fails. Representative comparisons are below; every expectation is recorded in
`tests/External/WordPressExperimentalTest.php`.

All paths in this table are relative to `wp-includes/`.

| Target and selected read | Definitions found and confirmed in source |
|---|---|
| `sanitize_key`, `formatting.php:2207`, `$sanitized_key` | Initial assignment at 2192 or sanitized assignment at 2196; the intermediate value at 2195 has been overwritten. |
| `wp_parse_list`, `functions.php:5050`, `$input_list` | Filtered array assignment at 5048; the string-input branch returns at 5044. |
| `wp_extract_urls`, `functions.php:862`, `$post_links` | The `preg_match_all` output argument at 851, rather than an input variable read. |
| `wp_array_slice_assoc`, `functions.php:5118`, `$slice` | Empty array at 5110 or aggregate write at 5114 across loop iterations. |
| `sanitize_file_name`, `formatting.php:2121`, `$filename` | Initial part at 2095, appended part at 2104, or conditional suffix at 2116. |
| `wp_strip_all_tags`, `formatting.php:5635`, `$text` | Assignment at 5629 or optional whitespace removal at 5632. |
| `wp_check_filetype`, `functions.php:3096`, implicit `$ext` | Default `false` at 3085 or matched extension at 3091. |
| `WP_REST_Request::get_content_type`, `rest-api/class-wp-rest-request.php:333`, implicit `$parameters` | Empty string at 320 or split result at 322. |
| `WP_Hook::apply_filters`, `class-wp-hook.php:365`, `$value` | Original parameter at 328 or one of the three callback results at 351, 353, and 355. |
| `WP_List_Util::pluck`, `class-wp-list-util.php:211`, `$newlist` | Initialization at 159 or writes at 192, 194, 198, and 200; writes at 168 and 170 belong to the branch that already returned. |
| `WP_Error::get_error_message`, `class-wp-error.php:140`, `$code` | Parameter at 136 or fallback assignment at 138. |
| `wpdb::prepare`, `class-wpdb.php:1753`, `$value` | Current iteration at 1731 or compatibility coercion at 1750. |

The other checks establish that:

- `sanitize_key`'s initial value reaches the final read but not the overwritten sanitizer
  input; the lowercase assignment reaches the next regex input.
- The matched extension in `wp_check_filetype` reaches the implicit read, `compact()`
  call, and return in three reverse edges.
- `WP_REST_Request::get_content_type`'s split parameters reach the implicit compact read.
- The extension write requires iteration at 3087 and a truthy regex match at 3089.
- The sanitizer branch requires scalar input at 2194.
- `WP_Hook::apply_filters` records the nonempty-callback path at 329, iteration at 344,
  the zero-argument branch at 350, and the do-loop repeat condition at 358. The `repeat`
  label applies to subsequent iterations; it is not a prerequisite for the first one.
- `wp_parse_args`, `wp_normalize_path`, and `wp_unique_id` return nonzero command status
  with the expected explicit refusal for reference aliases or shared static storage.

## Omission found and fixed

The first 28 manually checked cases produced 23 matches and five failures. All five
failures involved `compact()`: ordinary argument analysis saw string literals but missed
the local variables they name. This left no selectable variable occurrence at the return
in `wp_check_filetype` and broke the path from its assignments to the returned value.
`WP_REST_Request::get_content_type` had the same omission.

PHP documents that [compact reads the current symbol table](https://www.php.net/manual/en/function.compact.php).
The experimental analyzer now records `implicit-read` occurrences at literal names,
connecting them to the current reaching definitions and the call. Nested unkeyed literal
lists are supported. Dynamic names, keyed arrays and unpacking are explicitly rejected
instead of guessing. Callable creation and shadowing by written function declarations
keep their ordinary call semantics. No production analyzer was changed.

After the correction, all original 28 expectations passed. Twelve further cases in the
hook, list utility, error, and database classes also passed. Unit regressions additionally
cover overwrites, conditional alternatives, undefined/unset locals, nested name lists,
qualified builtin names, callable creation, shadowing, and unsupported name selection.

## What the corpus does not establish

A consistent graph does not prove semantic completeness. Manual checks cover the stated
51 cases, rather than all occurrences in the 9,912 accepted callables. Call returns and
heap contents remain explicit boundaries; array elements share an aggregate; separate
conditions are not solved for joint feasibility. WordPress hook execution, object state,
database behavior, and interprocedural flow are not claimed to be resolved.

The 2,029 refusals are part of the reported coverage, not successful analyses. Each
callable contributes its first refusal reason:

| Reason | Callables |
|---|---:|
| Shared global/static storage | 1,316 |
| Inline HTML statements | 244 |
| Dynamic execution or suspension | 154 |
| Reference aliases | 146 |
| Try/catch/finally | 145 |
| Goto | 16 |
| Closure captures by reference | 6 |
| Calls introducing local variables | 1 |
| Variable variables | 1 |

## Reproduce

Run from the peq checkout. The archive stays under ignored `var/cache/`; no WordPress source
is redistributed in this repository. The external tests are deliberately outside the
default PHPUnit suites and require the pinned download.

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

bin/console experimental inspect wp_check_filetype \
  var/cache/wordpress-validation/7.1.1/wordpress/wp-includes/functions.php \
  --line 3096 --variable ext --level 1 --output=json --php-version=7.4

bin/console experimental inspect wp_check_filetype \
  var/cache/wordpress-validation/7.1.1/wordpress/wp-includes/functions.php \
  --line 3091 --variable ext --direction=used-by --level 3 \
  --output=json --php-version=7.4
```

The first command selects the implicit `$ext` read at line 3096 and returns definitions
at 3085 and 3091. The second exposes the corrected path through `compact()` to the return.
The external PHPUnit tests repeat the graph comparisons, refusal checks, source hashes,
and whole-corpus structural audit; the CLI comparisons were also executed separately
during this validation.
