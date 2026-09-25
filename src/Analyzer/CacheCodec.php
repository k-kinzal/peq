<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;

/**
 * Stores passive analysis values in independently checked, bounded graph batches.
 *
 * A graph is never serialized as one string. Its inverse edges and lookup indexes
 * are rebuilt on read, while a syntax entry holds only a single file's tree.
 */
final class CacheCodec
{
    /**
     * Rejects implausible record lengths before allocating their payloads.
     */
    private const int MAX_RECORD_BYTES = 64 * 1024 * 1024;

    /**
     * Encodes small batches, ending with a digest of their order and completeness.
     *
     * @return iterable<string>
     */
    public static function encode(object $value): iterable
    {
        $kind = $value instanceof Graph ? "graph\n" : "value\n";

        yield $kind;
        $digest = hash_init('sha256');
        hash_update($digest, $kind);
        foreach (self::batches($value) as $batch) {
            $payload = serialize($batch);
            if (strlen($payload) > self::MAX_RECORD_BYTES) {
                /** No terminator means this optional cache entry cannot be reused. */
                return;
            }
            $header = strlen($payload).' '.hash('sha256', $payload)."\n";
            hash_update($digest, $header);

            yield $header;

            yield $payload;
        }

        yield '0 '.hash_final($digest)."\n";
    }

    /**
     * Keeps serialization's object table proportional to a batch, not the project.
     *
     * @return iterable<list<object>>
     */
    public static function batches(object $value): iterable
    {
        if (!$value instanceof Graph) {
            yield [$value];

            return;
        }
        $batch = [];
        foreach ($value->elements() as $element) {
            $batch[] = $element;
            if (count($batch) === 256) {
                yield $batch;
                $batch = [];
            }
        }
        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * Restores a complete stream, treating old formats and damaged data as misses.
     *
     * @param resource $stream
     */
    public static function decode($stream): ?object
    {
        $kind = fgets($stream, 16);
        if ($kind !== "graph\n" && $kind !== "value\n") {
            return null;
        }
        $digest = hash_init('sha256');
        hash_update($digest, $kind);
        $value = $kind === "graph\n" ? new Graph() : null;
        while (($header = fgets($stream, 96)) !== false) {
            if (preg_match('/^(0|[1-9][0-9]{0,8}) ([a-f0-9]{64})\n$/D', $header, $parts) !== 1) {
                return null;
            }
            $length = (int) $parts[1];
            if ($length === 0) {
                return hash_final($digest) === $parts[2] && fgetc($stream) === false ? $value : null;
            }
            if ($length > self::MAX_RECORD_BYTES) {
                return null;
            }
            hash_update($digest, $header);
            $payload = stream_get_contents($stream, $length);
            if ($payload === false || strlen($payload) !== $length || hash('sha256', $payload) !== $parts[2]) {
                return null;
            }
            $batch = self::restore($payload);
            unset($payload);
            if ($batch === null) {
                return null;
            }
            $value = self::append($value, $batch, $kind === "graph\n");
            if ($value === null) {
                return null;
            }
        }

        return null;
    }

    /**
     * Admits only the passive graph and syntax classes peq writes itself.
     *
     * @return null|list<object>
     */
    public static function restore(string $payload): ?array
    {
        if (!str_starts_with($payload, 'a:')) {
            return null;
        }
        $classes = [];
        $offset = 0;
        while (preg_match('/[OCE]:\d+:"([^"\x00]+)"/', $payload, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $class = explode(':', $match[1][0], 2)[0];
            if ($class !== CachedSyntax::class
                && !str_starts_with($class, 'App\Analyzer\Graph\\')
                && !str_starts_with($class, 'PhpParser\Node\\')
                && $class !== 'PhpParser\Comment' && $class !== 'PhpParser\Comment\Doc'
            ) {
                return null;
            }
            $classes[$class] = true;
        }
        $values = @unserialize($payload, ['allowed_classes' => array_keys($classes)]);
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            return null;
        }
        $batch = [];
        foreach ($values as $value) {
            if (!is_object($value)) {
                return null;
            }
            $batch[] = $value;
        }

        return $batch;
    }

    /**
     * Rebuilds derived graph storage while rejecting a stream of the wrong shape.
     *
     * @param list<object> $batch
     */
    public static function append(?object $value, array $batch, bool $graph): ?object
    {
        if (!$graph) {
            return $value === null && count($batch) === 1 ? $batch[0] : null;
        }
        if (!$value instanceof Graph) {
            return null;
        }
        foreach ($batch as $element) {
            if ($element instanceof Node) {
                $value->addNode($element);
            } elseif ($element instanceof Edge) {
                $value->addEdge($element);
            } else {
                return null;
            }
        }

        return $value;
    }
}
