<?php

declare(strict_types=1);

namespace App\Analyzer;

/**
 * Reads only the passive graph and syntax values written by the analysis cache.
 *
 * Checksums reject truncated or damaged entries before deserialization. Arbitrary
 * application classes and their deserialization hooks are never admitted.
 */
final class CacheCodec
{
    /**
     * Encodes an analysis value and a digest of its complete serialized contents.
     */
    public static function encode(object $value): string
    {
        $payload = serialize($value);

        return hash('sha256', $payload)."\n".$payload;
    }

    /**
     * Restores an intact passive value, or treats damage as a cache miss.
     */
    public static function decode(string $entry): ?object
    {
        $payload = substr($entry, 65);
        if (substr($entry, 0, 65) !== hash('sha256', $payload)."\n") {
            return null;
        }
        preg_match_all('/[OCE]:\d+:"([^"]+)"/', $payload, $matches);
        $classes = array_values(array_unique(array_map(static fn (string $name): string => explode(':', $name, 2)[0], $matches[1])));
        foreach ($classes as $class) {
            if ($class !== CachedSyntax::class
                && !str_starts_with($class, 'App\Analyzer\Graph\\')
                && !str_starts_with($class, 'PhpParser\Node\\')
                && $class !== 'PhpParser\Comment' && $class !== 'PhpParser\Comment\Doc'
            ) {
                return null;
            }
        }

        $value = @unserialize($payload, ['allowed_classes' => $classes]);

        return is_object($value) ? $value : null;
    }
}
