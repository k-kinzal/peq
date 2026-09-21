<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;

/**
 * The clause and subclause numbers of ISO/IEC 39075, read off its table of contents.
 *
 * Every scenario of this suite says which subclause of the standard it states, and a
 * number is the easiest thing in a specification to get wrong: the digits look right,
 * the topic beside them is one a reader recognises, and nothing complains. So the
 * numbers are looked up, and a scenario pointing at a subclause the standard does not
 * number fails the run.
 *
 * ISO publishes no machine-readable form of its own table of contents, so this is the
 * one list under `spec/iso/` that is transcribed rather than downloaded. It keeps the
 * numbers only: they are facts about the document, where the titles are ISO's text.
 *
 * @see spec/iso/subclauses.txt Where the numbers are read from
 */
final class IsoSubclauses
{
    /**
     * Where the transcription sits.
     */
    private const TRANSCRIPTION = __DIR__.'/../iso/subclauses.txt';

    /**
     * Reports whether the standard numbers a clause or subclause.
     *
     * @param string $number The number, as a tag writes it
     *
     * @return bool True when it does
     *
     * @throws RuntimeException If the transcription cannot be read
     */
    public static function numbers(string $number): bool
    {
        return in_array($number, self::all(), true);
    }

    /**
     * Returns every clause and subclause number the standard has.
     *
     * @return list<string> The numbers, in document order
     *
     * @throws RuntimeException If the transcription cannot be read
     */
    public static function all(): array
    {
        static $numbers = null;
        if ($numbers !== null) {
            return $numbers;
        }

        $read = @file_get_contents(self::TRANSCRIPTION);
        if ($read === false) {
            throw new RuntimeException(sprintf('Cannot read the subclause numbers at %s', self::TRANSCRIPTION));
        }

        $numbers = [];
        foreach (explode("\n", $read) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $numbers[] = $line;
            }
        }

        return $numbers;
    }
}
