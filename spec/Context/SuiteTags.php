<?php

declare(strict_types=1);

namespace Spec\Context;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The feature codes the scenarios of this suite state.
 *
 * The conformance register is a list of claims, and a list is easy to write and easy to
 * inflate. What stops it being inflated is this: the register is compared with the tags
 * of the suite, and a feature claimed by the register and stated by no scenario fails
 * the run.
 *
 * Reading the tags out of the feature files rather than out of Behat's own runtime keeps
 * the check whole. Behat would only ever offer the scenarios of the current run, so a
 * `--tags` filter would quietly shrink the evidence; the files are the whole suite
 * whatever is being run.
 */
final class SuiteTags
{
    /**
     * Where the feature files sit.
     */
    private const FEATURES = __DIR__.'/../features';

    /**
     * Returns every feature code a scenario of this suite is tagged with.
     *
     * @return list<string> The codes, without repetition
     */
    public static function featureCodes(): array
    {
        static $codes = null;
        if ($codes !== null) {
            return $codes;
        }

        $found = [];
        foreach (self::sources() as $source) {
            if (preg_match_all('/@feature:([A-Z0-9]+)/', $source, $matched) === 0) {
                continue;
            }
            foreach ($matched[1] as $code) {
                $found[$code] = true;
            }
        }
        $codes = array_keys($found);
        sort($codes);

        return $codes;
    }

    /**
     * Returns every subclause a scenario of this suite is tagged with.
     *
     * @return list<string> The subclause numbers, without repetition
     */
    public static function subclauses(): array
    {
        $found = [];
        foreach (self::sources() as $source) {
            if (preg_match_all('/@iso:([0-9]+(?:\.[0-9]+)*)/', $source, $matched) === 0) {
                continue;
            }
            foreach ($matched[1] as $number) {
                $found[$number] = true;
            }
        }
        $numbers = array_map(static fn (int|string $number): string => (string) $number, array_keys($found));
        sort($numbers);

        return $numbers;
    }

    /**
     * Returns the text of every feature file of this suite.
     *
     * @return list<string> The sources
     */
    public static function sources(): array
    {
        $sources = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::FEATURES));
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'feature') {
                continue;
            }
            $read = file_get_contents($file->getPathname());
            if ($read !== false) {
                $sources[] = $read;
            }
        }

        return $sources;
    }
}
