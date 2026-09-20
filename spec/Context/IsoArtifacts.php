<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;

/**
 * The artifacts this specification is written against, and the sums they arrived under.
 *
 * Everything else here reads one of these files and believes it. That is only worth
 * something while the files are the ones ISO published, and "we copied them carefully"
 * is exactly the kind of claim this suite exists to stop making: an artifact edited
 * until it agrees with the implementation would make every step pass and mean nothing.
 *
 * `spec/iso/SHA256SUMS` records the sum each file arrived under, and this compares them.
 * It catches drift, not forgery — someone who edits an artifact and its recorded sum
 * together has written a different specification, and the remedy for that is the URL in
 * `spec/iso/README.md`, which anybody can fetch and compare.
 *
 * @see spec/iso/README.md Where the artifacts come from
 */
final class IsoArtifacts
{
    /**
     * Where the sums sit.
     */
    private const SUMS = __DIR__.'/../iso/SHA256SUMS';

    /**
     * Where the repository this specification lives in begins.
     */
    private const ROOT = __DIR__.'/../..';

    /**
     * Returns every artifact whose sum was recorded, and the sum it arrived under.
     *
     * @return array<string, string> The SHA-256 sum, by path from the repository root
     *
     * @throws RuntimeException If the sums cannot be read
     */
    public static function recorded(): array
    {
        $read = @file_get_contents(self::SUMS);
        if ($read === false) {
            throw new RuntimeException(sprintf('Cannot read the recorded sums at %s', self::SUMS));
        }

        $sums = [];
        foreach (explode("\n", trim($read)) as $line) {
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line), 2);
            if ($parts === false || count($parts) !== 2) {
                throw new RuntimeException(sprintf('Cannot read "%s" as a recorded sum.', $line));
            }
            $sums[trim($parts[1])] = trim($parts[0]);
        }

        return $sums;
    }

    /**
     * Returns the sum an artifact has now, as it sits in the repository.
     *
     * @param string $path The path from the repository root
     *
     * @return null|string The sum, or null when there is no such file
     */
    public static function sumOf(string $path): ?string
    {
        $file = self::ROOT.'/'.$path;
        if (!is_file($file)) {
            return null;
        }
        $sum = @hash_file('sha256', $file);

        return $sum === false ? null : $sum;
    }
}
