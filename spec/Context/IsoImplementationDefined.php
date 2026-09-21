<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;
use SimpleXMLElement;

/**
 * The items ISO/IEC 39075 leaves for an implementation to define.
 *
 * The artifact's header says it exists so implementers can be sure every such item has
 * been given a definition, and so users can state what they need one to be. That is the
 * difference between a limit and a divergence: `--hops` caps the upper bound of an
 * unbounded quantifier, which sounds like peq answering a different question from the
 * one asked until you find IL018, where the standard says the cap is the
 * implementation's to choose.
 *
 * @see spec/iso/artifacts.txt Where ISO publishes the artifact, and its sum
 */
final class IsoImplementationDefined
{
    /**
     * Returns the item the standard gives a code, as it words it.
     *
     * @param string $code The code
     *
     * @return null|string The item, or null when the standard defines no such code
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function itemOf(string $code): ?string
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Returns every item the standard leaves to an implementation.
     *
     * @return array<string, string> The item, by code
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function all(): array
    {
        static $items = null;
        if ($items !== null) {
            return $items;
        }

        $read = @simplexml_load_file(IsoArtifacts::path('implementation-defined.xml'));
        if (!$read instanceof SimpleXMLElement) {
            throw new RuntimeException(sprintf('Cannot read the ISO implementation-defined artifact at %s', IsoArtifacts::path('implementation-defined.xml')));
        }

        $items = [];
        foreach ($read->impDef as $item) {
            $items[trim((string) $item->code)] = trim((string) $item->description);
        }

        return $items;
    }
}
