<?php

declare(strict_types=1);

namespace App\Gql\Datum;

use Override;

/**
 * A character string, which GQL calls a STRING.
 *
 * Most of what a query about source code returns is one of these: a symbol name, a
 * file path, a visibility, an attribute. They carry no quotes of their own — quoting
 * is a decision the reporter makes about the format it is writing, not a property of
 * the value.
 */
final class StringDatum implements Datum
{
    /**
     * @param string $value The characters
     */
    public function __construct(
        public readonly string $value,
    ) {}

    /**
     * Returns the string for a PHP string.
     *
     * @param string $value The characters
     *
     * @example A string is the characters it holds
     *     \App\Gql\Datum\StringDatum::of('App\\Domain\\Invoice')->value // => 'App\\Domain\\Invoice'
     *
     * @return self The string
     */
    public static function of(string $value): self
    {
        return new self($value);
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function kind(): DatumKind
    {
        return DatumKind::Text;
    }

    /**
     * Writes the string out the way a result shows it.
     *
     * @example A string shows as itself, with no quoting of its own
     *     (new \App\Gql\Datum\StringDatum('App\\Domain\\Invoice'))->toText() // => 'App\\Domain\\Invoice'
     *
     * @return string The characters
     */
    #[Override]
    public function toText(): string
    {
        return $this->value;
    }
}
