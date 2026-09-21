<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;
use SimpleXMLElement;

/**
 * The optional language features ISO/IEC 39075 defines, read from ISO's own artifact.
 *
 * Clause 24.3 makes a conformance claim a statement about these codes. Reading them out
 * of the artifact rather than copying them into PHP is the point: the claim in
 * `spec/features/conformance.feature` is checked against the file ISO publishes, so a
 * code that does not exist, a description that has drifted, or a feature the claim
 * forgot to answer for is a failing scenario rather than a thing somebody has to notice.
 *
 * @see spec/iso/artifacts.txt Where ISO publishes the artifact, and its sum
 */
final class IsoFeatures
{
    /**
     * Returns every optional feature the standard defines.
     *
     * @return array<string, string> The description, by feature code
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function all(): array
    {
        static $features = null;
        if ($features !== null) {
            return $features;
        }

        $read = @simplexml_load_file(IsoArtifacts::path('features.xml'));
        if (!$read instanceof SimpleXMLElement) {
            throw new RuntimeException(sprintf('Cannot read the ISO feature artifact at %s', IsoArtifacts::path('features.xml')));
        }

        $features = [];
        foreach ($read->feature as $feature) {
            $features[trim((string) $feature->code)] = trim((string) $feature->description);
        }

        return $features;
    }
}
