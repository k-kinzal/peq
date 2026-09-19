<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * The absence of a value, which GQL also reads as the unknown truth value.
 *
 * GQL has three truth values, and the third one is this: a comparison that cannot be
 * decided evaluates to unknown, and unknown is null. Giving them one representation
 * rather than two is what makes `p.nickname = 'x'` and `NULL AND TRUE` behave the
 * same way in a filter — both are not true, so both are removed — without any rule
 * saying so twice.
 */
final class NullDatum implements Datum
{
    /**
     * Returns the absence of a value.
     *
     * Written as a named constructor rather than a plain `new` because that is how it
     * reads at a call site: a comparison does not build a null, it comes out unknown.
     *
     * @example A comparison that cannot be decided comes out unknown
     *     \App\Gql\Datum\NullDatum::unknown()->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return self The absent value
     */
    public static function unknown(): self
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Null;
    }

    /**
     * Writes the absent value out the way a result shows it.
     *
     * @example An absent value is shown as the word GQL writes for it
     *     (new \App\Gql\Datum\NullDatum())->toText() // => 'NULL'
     *
     * @return string The word GQL writes for it
     */
    #[Override]
    public function toText(): string
    {
        return 'NULL';
    }
}
