<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;

/**
 * The clause and subclause structure of ISO/IEC 39075, transcribed from its contents.
 *
 * Every scenario of this suite says which subclause of the standard it states, and a
 * number is the easiest thing in a specification to get wrong: the four digits look
 * right, the title beside them is one a reader recognises, and nothing complains. So
 * the numbers are looked up, and a scenario pointing at a subclause the standard does
 * not number fails the run.
 *
 * This is the one file under `spec/iso/` that is not an ISO artifact — ISO publishes no
 * machine-readable form of its own table of contents — so it carries its provenance in
 * its header and reproduces numbers and titles only.
 *
 * @see spec/iso/subclauses.txt Where it comes from
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
        return isset(self::all()[$number]);
    }

    /**
     * Returns the title the standard gives a clause or subclause.
     *
     * @param string $number The number
     *
     * @return null|string The title, or null when the standard numbers no such thing
     *
     * @throws RuntimeException If the transcription cannot be read
     */
    public static function titleOf(string $number): ?string
    {
        return self::all()[$number] ?? null;
    }

    /**
     * Returns every clause and subclause the standard numbers, by number.
     *
     * @return array<string, string> The title, by number
     *
     * @throws RuntimeException If the transcription cannot be read
     */
    public static function all(): array
    {
        static $subclauses = null;
        if ($subclauses !== null) {
            return $subclauses;
        }

        $read = @file_get_contents(self::TRANSCRIPTION);
        if ($read === false) {
            throw new RuntimeException(sprintf('Cannot read the subclause transcription at %s', self::TRANSCRIPTION));
        }

        $subclauses = [];
        foreach (explode("\n", $read) as $line) {
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, "\t")) {
                continue;
            }
            [$number, $title] = explode("\t", $line, 2);
            $subclauses[trim($number)] = trim($title);
        }

        return $subclauses;
    }
}
