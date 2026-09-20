<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;
use SimpleXMLElement;

/**
 * The conditions ISO/IEC 39075 defines, read from ISO's own artifact.
 *
 * Clause 23 says a GQL-implementation reports what happened as a five-character
 * GQLSTATUS, and the standard publishes every one it defines. A code outside that list
 * is a code no other implementation of GQL will ever report, which makes it worse than
 * a bad message: a caller testing for it is testing for nothing.
 *
 * The artifact's own header says it exists so that implementers can report the
 * standard's natural-language text, so the wording is checked here too.
 *
 * @see spec/iso/README.md Where the artifact comes from and how to verify it
 */
final class IsoConditions
{
    /**
     * Where the artifact sits.
     */
    private const ARTIFACT = __DIR__.'/../iso/conditions.xml';

    /**
     * How the standard's categories are read out in front of a condition.
     */
    private const CATEGORIES = [
        'S' => 'note',
        'N' => 'note',
        'I' => 'note',
        'W' => 'warning',
        'X' => 'error',
    ];

    /**
     * Reports whether the standard defines a five-character code.
     *
     * @param string $code The code
     *
     * @return bool True when it does
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function defines(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    /**
     * Returns the condition the standard pairs with a code, as it is read out.
     *
     * @param string $code The code
     *
     * @return null|string The condition, or null when the standard defines no such code
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function conditionOf(string $code): ?string
    {
        $found = self::all()[$code] ?? null;

        return $found === null ? null : self::CATEGORIES[$found[0]].': '.$found[1];
    }

    /**
     * Returns every condition the standard defines.
     *
     * @return array<string, array{string, string}> The category and the condition, by code
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function all(): array
    {
        static $conditions = null;
        if ($conditions !== null) {
            return $conditions;
        }

        $read = @simplexml_load_file(self::ARTIFACT);
        if (!$read instanceof SimpleXMLElement) {
            throw new RuntimeException(sprintf('Cannot read the ISO condition artifact at %s', self::ARTIFACT));
        }

        $conditions = [];
        foreach ($read->class as $class) {
            $code = (string) $class['code'];
            $name = (string) $class['name'];
            $category = (string) $class['category'];
            $conditions[$code.'000'] = [$category, $name];
            foreach ($class->subclass as $subclass) {
                $conditions[$code.(string) $subclass['code']] = [$category, $name.' - '.(string) $subclass['name']];
            }
        }

        return $conditions;
    }
}
