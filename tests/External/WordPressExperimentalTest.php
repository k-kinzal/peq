<?php

declare(strict_types=1);

namespace Tests\External;

use App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer;
use App\Analyzer\Graph\Direction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * Manually audited expectations for the separately downloaded WordPress 7.1.1 release.
 *
 * See docs/wordpress-variable-validation.md for the pinned archive and invocation.
 * This external corpus is deliberately outside the default test suites.
 *
 * @internal
 */
#[CoversNothing]
#[Large]
final class WordPressExperimentalTest extends TestCase
{
    /**
     * Ensures line-based expectations refer to the exact audited source files.
     */
    #[DataProvider('providerSourceHashes')]
    public function testAuditedSourceFilesMatchThePinnedRelease(string $file, string $expected): void
    {
        $path = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress/'.$file;
        self::assertFileExists($path);
        self::assertSame($expected, hash_file('sha256', $path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSourceHashes(): iterable
    {
        yield 'wp-includes/class-wp-error.php' => ['wp-includes/class-wp-error.php', 'a9e971548bf6a1d93c821f980f530efba985c25d5d0b13d9b63e63b378a81a94'];

        yield 'wp-includes/class-wp-hook.php' => ['wp-includes/class-wp-hook.php', 'b839c0e5672246bca8db1ab781ec8835f7732f253c375a237cbf6ec536e8d12e'];

        yield 'wp-includes/class-wp-list-util.php' => ['wp-includes/class-wp-list-util.php', '90171523d0b89315e0cba603b03dc177998de8f3e9895a956cbeed42955a085b'];

        yield 'wp-includes/class-wpdb.php' => ['wp-includes/class-wpdb.php', 'e15403e90032dd0508811301505e491d4212de5152581a98f51b744a1a3903bd'];

        yield 'wp-includes/formatting.php' => ['wp-includes/formatting.php', '350f6b32303915df970bb0e3ea360fcd54bb0b8a7ecc3d504c3d860ab9468d32'];

        yield 'wp-includes/functions.php' => ['wp-includes/functions.php', 'cac8a17e293df99f0683a88debd3ba5e5cce274183a4e9fd3c9f18375747f41c'];

        yield 'wp-includes/rest-api/class-wp-rest-request.php' => ['wp-includes/rest-api/class-wp-rest-request.php', '8d2f61dcafedacad262118874665291c6ced87b231479d9f18fcc58c06508b09'];
    }

    /**
     * @param list<array{int, string}> $expected
     */
    #[DataProvider('providerReads')]
    public function testReadsHaveTheManuallyAuditedReachingDefinitions(string $target, string $file, int $line, string $variable, array $expected): void
    {
        $path = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress/'.$file;
        self::assertFileExists($path);
        $graph = (new ExperimentAnalyzer(phpVersion: 70400))->inspect($path, $target);
        $roots = $graph->select($line, $variable);
        $edges = array_filter($graph->edges, static fn (Dependency $edge): bool => in_array($edge->from, $roots, true) && $edge->kind === 'reaching-definition');
        $actual = array_values(array_unique(array_map(static fn (Dependency $edge): array => [$graph->nodes[$edge->to]->line, $graph->nodes[$edge->to]->kind], $edges), SORT_REGULAR));
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, string, int, string, list<array{int, string}>}>
     */
    public static function providerReads(): iterable
    {
        yield 'sanitize-key-input' => ['sanitize_key', 'wp-includes/formatting.php', 2196, 'sanitized_key', [[2195, 'write']]];

        yield 'sanitize-key-merge' => ['sanitize_key', 'wp-includes/formatting.php', 2207, 'sanitized_key', [[2192, 'write'], [2196, 'write']]];

        yield 'sanitize-key-original' => ['sanitize_key', 'wp-includes/formatting.php', 2207, 'key', [[2191, 'parameter']]];

        yield 'parse-list-return' => ['wp_parse_list', 'wp-includes/functions.php', 5050, 'input_list', [[5048, 'write']]];

        yield 'parse-list-filter-input' => ['wp_parse_list', 'wp-includes/functions.php', 5048, 'input_list', [[5041, 'parameter']]];

        yield 'parse-list-early-return' => ['wp_parse_list', 'wp-includes/functions.php', 5044, 'parsed_list', [[5043, 'write']]];

        yield 'extract-urls-regex-output' => ['wp_extract_urls', 'wp-includes/functions.php', 862, 'post_links', [[851, 'call-write']]];

        yield 'extract-urls-return' => ['wp_extract_urls', 'wp-includes/functions.php', 866, 'post_links', [[854, 'write']]];

        yield 'array-slice-accumulator' => ['wp_array_slice_assoc', 'wp-includes/functions.php', 5118, 'slice', [[5110, 'write'], [5114, 'aggregate-write']]];

        yield 'array-slice-key' => ['wp_array_slice_assoc', 'wp-includes/functions.php', 5114, 'key', [[5112, 'write']]];

        yield 'array-slice-source' => ['wp_array_slice_assoc', 'wp-includes/functions.php', 5114, 'input_array', [[5109, 'parameter']]];

        yield 'filename-parts-after-shift' => ['sanitize_file_name', 'wp-includes/formatting.php', 2096, 'parts', [[2095, 'call-write']]];

        yield 'filename-parts-after-pop' => ['sanitize_file_name', 'wp-includes/formatting.php', 2103, 'parts', [[2096, 'call-write']]];

        yield 'filename-final' => ['sanitize_file_name', 'wp-includes/formatting.php', 2131, 'filename', [[2121, 'write']]];

        yield 'filename-loop-carried' => ['sanitize_file_name', 'wp-includes/formatting.php', 2121, 'filename', [[2095, 'write'], [2104, 'write'], [2116, 'write']]];

        yield 'filename-break-merge' => ['sanitize_file_name', 'wp-includes/formatting.php', 2115, 'allowed', [[2107, 'write'], [2111, 'write']]];

        yield 'filename-original-copy' => ['sanitize_file_name', 'wp-includes/formatting.php', 2131, 'filename_raw', [[2036, 'write']]];

        yield 'strip-tags-optional-branch' => ['wp_strip_all_tags', 'wp-includes/formatting.php', 5635, 'text', [[5629, 'write'], [5632, 'write']]];

        yield 'strip-tags-original-input' => ['wp_strip_all_tags', 'wp-includes/formatting.php', 5628, 'text', [[5600, 'parameter']]];

        yield 'filetype-regex-output' => ['wp_check_filetype', 'wp-includes/functions.php', 3091, 'ext_matches', [[3089, 'call-write']]];

        yield 'filetype-iterator-reset' => ['wp_check_filetype', 'wp-includes/functions.php', 3088, 'ext_preg', [[3087, 'write']]];

        yield 'filetype-optional-defaults' => ['wp_check_filetype', 'wp-includes/functions.php', 3087, 'mimes', [[3080, 'parameter'], [3082, 'write']]];

        yield 'filetype-compact-extension' => ['wp_check_filetype', 'wp-includes/functions.php', 3096, 'ext', [[3085, 'write'], [3091, 'write']]];

        yield 'filetype-compact-type' => ['wp_check_filetype', 'wp-includes/functions.php', 3096, 'type', [[3084, 'write'], [3090, 'write']]];

        yield 'rest-compact-parameters' => ['WP_REST_Request::get_content_type', 'wp-includes/rest-api/class-wp-rest-request.php', 333, 'parameters', [[320, 'write'], [322, 'write']]];

        yield 'rest-compact-value' => ['WP_REST_Request::get_content_type', 'wp-includes/rest-api/class-wp-rest-request.php', 333, 'value', [[325, 'write']]];

        yield 'rest-compact-type' => ['WP_REST_Request::get_content_type', 'wp-includes/rest-api/class-wp-rest-request.php', 333, 'type', [[331, 'write']]];

        yield 'rest-compacted-result' => ['WP_REST_Request::get_content_type', 'wp-includes/rest-api/class-wp-rest-request.php', 334, 'data', [[333, 'write']]];

        yield 'hook-empty-return' => ['WP_Hook::apply_filters', 'wp-includes/class-wp-hook.php', 330, 'value', [[328, 'parameter']]];

        yield 'hook-loop-carried' => ['WP_Hook::apply_filters', 'wp-includes/class-wp-hook.php', 346, 'value', [[328, 'parameter'], [351, 'write'], [353, 'write'], [355, 'write']]];

        yield 'hook-return' => ['WP_Hook::apply_filters', 'wp-includes/class-wp-hook.php', 365, 'value', [[328, 'parameter'], [351, 'write'], [353, 'write'], [355, 'write']]];

        yield 'hook-mutable-args' => ['WP_Hook::apply_filters', 'wp-includes/class-wp-hook.php', 353, 'args', [[328, 'parameter'], [346, 'aggregate-write']]];

        yield 'pluck-early-branch' => ['WP_List_Util::pluck', 'wp-includes/class-wp-list-util.php', 180, 'newlist', [[159, 'write'], [168, 'aggregate-write'], [170, 'aggregate-write']]];

        yield 'pluck-later-branch' => ['WP_List_Util::pluck', 'wp-includes/class-wp-list-util.php', 211, 'newlist', [[159, 'write'], [192, 'aggregate-write'], [194, 'aggregate-write'], [198, 'aggregate-write'], [200, 'aggregate-write']]];

        yield 'error-optional-code' => ['WP_Error::get_error_message', 'wp-includes/class-wp-error.php', 140, 'code', [[136, 'parameter'], [138, 'write']]];

        yield 'error-return' => ['WP_Error::get_error_message', 'wp-includes/class-wp-error.php', 144, 'messages', [[140, 'write']]];

        yield 'wpdb-replaced-query' => ['wpdb::prepare', 'wp-includes/class-wpdb.php', 1505, 'query', [[1502, 'write']]];

        yield 'wpdb-optional-arg-array' => ['wpdb::prepare', 'wp-includes/class-wpdb.php', 1731, 'args', [[1458, 'parameter'], [1521, 'write']]];

        yield 'wpdb-coerced-value' => ['wpdb::prepare', 'wp-includes/class-wpdb.php', 1753, 'value', [[1731, 'write'], [1750, 'write']]];

        yield 'wpdb-escaped-args' => ['wpdb::prepare', 'wp-includes/class-wpdb.php', 1757, 'args_escaped', [[1729, 'write'], [1733, 'aggregate-write'], [1735, 'aggregate-write'], [1753, 'aggregate-write']]];
    }

    /**
     * @param list<array{int, string}> $expected
     */
    #[DataProvider('providerReverseReads')]
    public function testWritesReachTheAuditedUsesInReverse(string $target, string $file, int $line, string $variable, array $expected): void
    {
        $path = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress/'.$file;
        $graph = (new ExperimentAnalyzer(phpVersion: 70400))->inspect($path, $target);
        $slice = Slice::of($graph, $graph->select($line, $variable), Direction::UsedBy, 1);
        $edges = array_filter($slice->edges, static fn (Dependency $edge): bool => $edge->kind === 'reaching-definition');
        $actual = array_values(array_map(static fn (Dependency $edge): array => [$graph->nodes[$edge->to]->line, $graph->nodes[$edge->to]->kind], $edges));
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, string, int, string, list<array{int, string}>}>
     */
    public static function providerReverseReads(): iterable
    {
        yield 'overwritten initial value does not reach the sanitizer branch' => ['sanitize_key', 'wp-includes/formatting.php', 2192, 'sanitized_key', [[2207, 'read']]];

        yield 'lowercased value reaches the regex input' => ['sanitize_key', 'wp-includes/formatting.php', 2195, 'sanitized_key', [[2196, 'read']]];

        yield 'matched extension reaches compact' => ['wp_check_filetype', 'wp-includes/functions.php', 3091, 'ext', [[3096, 'implicit-read']]];

        yield 'split parameters reach compact' => ['WP_REST_Request::get_content_type', 'wp-includes/rest-api/class-wp-rest-request.php', 322, 'parameters', [[333, 'implicit-read']]];
    }

    /**
     * @param list<array{int, string}> $expected
     */
    #[DataProvider('providerControlDependencies')]
    public function testWritesCarryTheAuditedBranchConditions(string $target, string $file, int $line, string $variable, array $expected): void
    {
        $path = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress/'.$file;
        $graph = (new ExperimentAnalyzer(phpVersion: 70400))->inspect($path, $target);
        $roots = $graph->select($line, $variable);
        $edges = array_filter($graph->edges, static fn (Dependency $edge): bool => in_array($edge->from, $roots, true) && $edge->kind === 'control');
        $actual = array_values(array_unique(array_map(static fn (Dependency $edge): array => [$graph->nodes[$edge->to]->line, $edge->branch], $edges), SORT_REGULAR));
        sort($actual);
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, string, int, string, list<array{int, string}>}>
     */
    public static function providerControlDependencies(): iterable
    {
        yield 'regex must match during an iteration' => ['wp_check_filetype', 'wp-includes/functions.php', 3091, 'ext', [[3087, 'iterate'], [3089, 'truthy']]];

        yield 'sanitizer only runs for scalar input' => ['sanitize_key', 'wp-includes/formatting.php', 2196, 'sanitized_key', [[2194, 'truthy']]];

        yield 'callback branch and repeated do body' => ['WP_Hook::apply_filters', 'wp-includes/class-wp-hook.php', 351, 'value', [[329, 'falsy'], [344, 'iterate'], [350, 'truthy'], [358, 'repeat']]];
    }

    /**
     * Checks the complete reverse path that was missing before implicit local reads.
     */
    public function testCompactConnectsTheMatchedExtensionToTheReturn(): void
    {
        $path = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress/wp-includes/functions.php';
        $graph = (new ExperimentAnalyzer(phpVersion: 70400))->inspect($path, 'wp_check_filetype');
        $slice = Slice::of($graph, $graph->select(3091, 'ext'), Direction::UsedBy, 3);
        self::assertSame(['write', 'implicit-read', 'call', 'return'], array_column($slice->nodes, 'kind'));
        self::assertSame([3091, 3096, 3096, 3096], array_column($slice->nodes, 'line'));
    }

    /**
     * Unsupported storage must not produce a successful partial graph.
     */
    #[DataProvider('providerUnsupportedStorage')]
    public function testUnsupportedStorageProducesAnExplicitRefusal(string $target, string $reason): void
    {
        $path = dirname(__DIR__, 2).'/var/cache/wordpress-validation/7.1.1/wordpress/wp-includes/functions.php';
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage($reason);
        (new ExperimentAnalyzer(phpVersion: 70400))->inspect($path, $target);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUnsupportedStorage(): iterable
    {
        yield 'reference alias' => ['wp_parse_args', 'reference aliases are not supported'];

        yield 'static cache' => ['wp_normalize_path', 'shared variable storage is not supported'];

        yield 'static counter' => ['wp_unique_id', 'shared variable storage is not supported'];
    }
}
