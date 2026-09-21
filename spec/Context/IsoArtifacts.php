<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;

/**
 * The ISO artifacts this specification is written against, fetched from ISO.
 *
 * ISO publishes the grammar, the feature list, the conditions and the
 * implementation-defined items of ISO/IEC 39075 as files anybody may download and use,
 * unmodified, for what the standard says they are for. What it does not grant is their
 * redistribution, so this repository — MIT-licensed — does not carry them. It carries
 * `spec/iso/artifacts.txt` instead: where ISO publishes each file, and the SHA-256 it is
 * published with. The first run downloads each one into `build/iso/`; every run checks
 * the sum before anything reads the file.
 *
 * The check is what makes the rest of this suite mean anything. Every other step reads
 * one of these files and believes it, and a file edited until it agreed with `src/`
 * would let every one of them pass.
 *
 * @see spec/iso/artifacts.txt Where each artifact comes from, and its sum
 */
final class IsoArtifacts
{
    /**
     * Where the list of artifacts sits.
     */
    private const MANIFEST = __DIR__.'/../iso/artifacts.txt';

    /**
     * Where downloaded artifacts are kept between runs.
     */
    private const CACHE = __DIR__.'/../../build/iso';

    /**
     * How long a download may take before the run gives up, in seconds.
     */
    private const TIMEOUT = 60;

    /**
     * Returns the local path of an artifact, downloading it from ISO if it is not cached.
     *
     * @param string $name The name the artifact is cached under
     *
     * @return string The path of a file whose sum is the one recorded for it
     *
     * @throws RuntimeException If the artifact is not listed, cannot be downloaded, or is not the file ISO published
     */
    public static function path(string $name): string
    {
        $listed = self::manifest()[$name] ?? null;
        if ($listed === null) {
            throw new RuntimeException(sprintf('spec/iso/artifacts.txt lists no artifact called "%s".', $name));
        }

        $file = self::CACHE.'/'.$name;
        if (self::sumOf($name) !== $listed['sum']) {
            self::download($listed['url'], $file);
        }
        if (self::sumOf($name) !== $listed['sum']) {
            throw new RuntimeException(sprintf(
                '%s is not the file ISO publishes at %s: its SHA-256 is not %s.',
                $file,
                $listed['url'],
                $listed['sum'],
            ));
        }

        return $file;
    }

    /**
     * Returns every artifact listed, with the sum and the URL it is published with.
     *
     * @return array<string, array{sum: string, url: string}> The artifacts, by the name they are cached under
     *
     * @throws RuntimeException If the list cannot be read
     */
    public static function manifest(): array
    {
        $read = @file_get_contents(self::MANIFEST);
        if ($read === false) {
            throw new RuntimeException(sprintf('Cannot read the artifact list at %s', self::MANIFEST));
        }

        $listed = [];
        foreach (explode("\n", $read) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if ($parts === false || count($parts) !== 3) {
                throw new RuntimeException(sprintf('Cannot read "%s" as a listed artifact.', $line));
            }
            $listed[$parts[1]] = ['sum' => $parts[0], 'url' => $parts[2]];
        }

        return $listed;
    }

    /**
     * Returns the sum a cached artifact has now.
     *
     * @param string $name The name the artifact is cached under
     *
     * @return null|string The sum, or null when it has not been downloaded
     */
    public static function sumOf(string $name): ?string
    {
        $file = self::CACHE.'/'.$name;
        if (!is_file($file)) {
            return null;
        }
        $sum = @hash_file('sha256', $file);

        return $sum === false ? null : $sum;
    }

    /**
     * Downloads one artifact from where ISO publishes it.
     *
     * @param string $url  Where ISO publishes it
     * @param string $file Where to keep it
     *
     * @throws RuntimeException If it cannot be downloaded or kept
     */
    private static function download(string $url, string $file): void
    {
        if (!is_dir(self::CACHE) && !@mkdir(self::CACHE, 0o777, true) && !is_dir(self::CACHE)) {
            throw new RuntimeException(sprintf('Cannot create %s to keep ISO artifacts in.', self::CACHE));
        }

        $context = stream_context_create(['http' => ['timeout' => self::TIMEOUT, 'user_agent' => 'peq-spec']]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            throw new RuntimeException(sprintf('Cannot download %s. composer spec reads ISO\'s artifacts from ISO, so it needs the network the first time it runs.', $url));
        }
        if (@file_put_contents($file, $body) === false) {
            throw new RuntimeException(sprintf('Cannot write %s.', $file));
        }
    }
}
