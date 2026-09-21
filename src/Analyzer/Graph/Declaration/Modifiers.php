<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Declaration;

/**
 * The keywords a declaration carries besides its visibility.
 *
 * PHP writes these as a set of independent flags rather than as one choice: a method
 * may be both final and static, a class both abstract and never final. They are kept
 * together as one value because they are read together — "the abstract methods", "the
 * readonly properties" — and because a declaration with nothing written on it still
 * has modifiers, all of them false, rather than no modifiers at all.
 *
 * Only the keywords that survive into the analysed graph are here. `readonly` on a
 * class and `readonly` on a property are the same keyword answering the same
 * question, so they share one field.
 */
final readonly class Modifiers
{
    /**
     * @param bool $static   Whether the declaration belongs to the class rather than to an instance
     * @param bool $abstract Whether the declaration has no body and must be provided by a subclass
     * @param bool $final    Whether the declaration may not be overridden
     * @param bool $readonly Whether the declaration may only be written once
     */
    public function __construct(
        public bool $static = false,
        public bool $abstract = false,
        public bool $final = false,
        public bool $readonly = false,
    ) {}

    /**
     * Reports whether the declaration carries no modifier at all.
     *
     * A declaration with nothing written on it is the common case, and telling it
     * apart from one that merely happens not to be abstract is what lets a report
     * leave the whole group out rather than print four falses.
     *
     * @example A plain declaration carries nothing
     *     (new \App\Analyzer\Graph\Declaration\Modifiers())->none() // => true
     * @example One keyword is enough to be worth reporting
     *     (new \App\Analyzer\Graph\Declaration\Modifiers(static: true))->none() // => false
     *
     * @return bool True when no modifier is set
     */
    public function none(): bool
    {
        return !$this->static && !$this->abstract && !$this->final && !$this->readonly;
    }
}
