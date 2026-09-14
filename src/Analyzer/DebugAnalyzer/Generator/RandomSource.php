<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

/**
 * The draws a generated graph is made of.
 *
 * Every choice the generators make — how many members a class declares, whether a
 * method calls another, which word a name is spelled with — is one of these three
 * draws. Naming them as a contract is what lets a graph be reproduced by replaying
 * the draws, whichever source they come from, and what keeps the generators from
 * reaching into anything else a random-data library happens to offer.
 *
 * @visibility parent
 */
interface RandomSource
{
    /**
     * Draws a number between two bounds, both included.
     *
     * @param int $min The smallest number that may be drawn
     * @param int $max The largest number that may be drawn
     *
     * @return int The drawn number
     */
    public function numberBetween(int $min, int $max): int;

    /**
     * Draws a boolean, true and false being equally likely.
     *
     * @return bool The drawn boolean
     */
    public function boolean(): bool;

    /**
     * Draws a lowercase word.
     *
     * @return string The drawn word
     */
    public function word(): string;
}
