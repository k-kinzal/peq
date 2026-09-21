<?php

declare(strict_types=1);

namespace Spec\Context;

use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every word the query engine gives a meaning of its own.
 *
 * The claim that peq speaks GQL rather than a dialect of it is only worth as much as
 * the way it is checked, and checking it against a list somebody wrote by hand is
 * worth nothing: the list and the code drift apart on the first change. So the list is
 * read out of the code. Every upper-case word written as a literal in `src/Gql` is one
 * the engine treats as part of the language, and the conformance feature asks ISO's
 * grammar about each.
 *
 * Docblocks are skipped, because an example that shows what a misspelling does — the
 * guide's own `RETRUN` — is prose about the language rather than part of it.
 */
final class EngineVocabulary
{
    /**
     * Where the query engine lives.
     */
    private const ENGINE = __DIR__.'/../../src/Gql';

    /**
     * Words the engine writes that no query can write back.
     *
     * These two are what an approximate number is called when it is not a number. They
     * are printed as the text of a value and are never read from a query, so GQL has
     * no reason to reserve them and peq has no reason to pretend it does.
     *
     * @var list<string> The words
     */
    private const WRITTEN_ONLY = ['INF', 'NAN'];

    /**
     * Returns every upper-case word the engine's code gives a meaning to.
     *
     * @return Generator<string, array{string, list<string>}> The word and the files it is written in, by word
     */
    public static function words(): Generator
    {
        $found = [];
        foreach (self::sources() as $path => $source) {
            $stripped = (string) preg_replace('~/\*.*?\*/~s', '', $source);
            if (preg_match_all('/\'([A-Z][A-Z_0-9]{1,})\'/', $stripped, $matched) === 0) {
                continue;
            }
            foreach ($matched[1] as $word) {
                $found[$word][$path] = true;
            }
        }
        ksort($found);

        foreach ($found as $word => $places) {
            if (in_array($word, self::WRITTEN_ONLY, true)) {
                continue;
            }

            yield $word => [$word, array_keys($places)];
        }
    }

    /**
     * Returns the source of every file the query engine is written from.
     *
     * @return array<string, string> The source, by the path it is read from
     */
    public static function sources(): array
    {
        $sources = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ENGINE));
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $read = file_get_contents($file->getPathname());
            if ($read !== false) {
                $sources['src/Gql/'.basename(dirname($file->getPathname())).'/'.$file->getBasename()] = $read;
            }
        }
        ksort($sources);

        return $sources;
    }
}
