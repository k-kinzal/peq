<?php

declare(strict_types=1);

namespace App\Analyzer;

use Closure;

/**
 * Independent, replaceable phase entries shared by inspections and graph queries.
 */
final readonly class PhaseCache
{
    /**
     * Uses one versioned store for all analysis phases.
     */
    public function __construct(private CacheStorage $storage) {}

    /**
     * Creates the command's cache in the directory the project runs in.
     */
    public static function inWorkingDirectory(): ?self
    {
        $directory = getcwd();

        return $directory === false ? null : new self(new CacheStorage($directory.'/.peq.cache', ExecutionVersion::current()));
    }

    /**
     * Reuses one phase when its complete input matches, otherwise replaces it.
     *
     * A slot identifies a file or an analysis configuration; its fingerprint changes
     * with the contents. Edits replace entries instead of retaining every revision.
     * Failures from the computation propagate and are never cached.
     *
     * @template T of object
     *
     * @param class-string<T> $type
     * @param Closure(): T    $compute
     *
     * @return T
     */
    public function remember(string $phase, string $slot, string $fingerprint, string $type, Closure $compute): object
    {
        $name = hash('sha256', $phase).'-'.hash('sha256', $slot).'.cache';
        $header = $phase."\n".$fingerprint."\n";
        $entry = $this->storage->locked(function () use ($name): ?string {
            $path = $this->storage->directory.'/'.$name;
            $contents = !is_link($path) && is_file($path) ? @file_get_contents($path) : false;

            return $contents === false ? null : $contents;
        });
        if ($entry !== null && str_starts_with($entry, $header)) {
            $value = CacheCodec::decode(substr($entry, strlen($header)));
            if ($value instanceof $type) {
                return $value;
            }
        }

        $value = $compute();
        $contents = $header.CacheCodec::encode($value);
        $this->storage->locked(function () use ($name, $contents): ?string {
            $this->storage->write($name, $contents);

            return null;
        });

        return $value;
    }
}
