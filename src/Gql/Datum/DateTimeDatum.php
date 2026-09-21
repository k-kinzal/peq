<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use DateTimeImmutable;
use Override;

/**
 * A moment in time with the offset it is written in, which GQL calls a ZONED DATETIME.
 *
 * A dependency graph of source code has little use for time of its own, but the
 * language has it, and a query that compares a graph written today against one
 * written last week needs it. Keeping the offset rather than normalising to UTC is
 * what GQL asks for: two moments that name the same instant in different offsets are
 * equal, and both still say where they were written.
 */
final readonly class DateTimeDatum implements Datum
{
    /**
     * @param DateTimeImmutable $value The moment
     */
    public function __construct(
        public DateTimeImmutable $value,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::DateTime;
    }

    /**
     * Writes the moment out the way a result shows it.
     *
     * ISO 8601 is the form GQL reads one in, so it is the form it writes one in:
     * a moment a query prints can be pasted back into a query that reads it.
     *
     * @example A moment is written in the form a query reads one in
     *     (new \App\Gql\Datum\DateTimeDatum(new \DateTimeImmutable('2024-01-15T10:30:00+00:00')))->toText() // => '2024-01-15T10:30:00+00:00'
     *
     * @return string The moment, in ISO 8601
     */
    #[Override]
    public function toText(): string
    {
        return $this->value->format('Y-m-d\TH:i:sP');
    }
}
