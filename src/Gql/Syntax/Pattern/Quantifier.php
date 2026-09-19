<?php

declare(strict_types=1);

namespace App\Gql\Syntax\Pattern;

/**
 * How many times a pattern may repeat.
 *
 * This is what turns "what does this call" into "what does this reach": `{1,3}` asks
 * for anything up to three steps away without the query having to be written three
 * times. A lower bound of zero matches nothing at all, which makes the two ends of
 * the pattern the same node — the way GQL says it should.
 *
 * The upper bound may be absent, meaning as far as the graph goes. On a graph with
 * cycles that is only finite because a path mode makes it so, which is why the
 * default mode forbids crossing an edge twice.
 */
final class Quantifier
{
    /**
     * @param int      $least The fewest repetitions that match
     * @param null|int $most  The most that match, or null for as many as the graph allows
     */
    public function __construct(
        public readonly int $least = 0,
        public readonly ?int $most = null,
    ) {
        assert($this->least >= 0, 'A pattern cannot repeat fewer than no times');
        assert($this->most === null || $this->most >= $this->least, 'A pattern cannot repeat fewer times at most than at least');
    }

    /**
     * Returns the quantifier for a pattern written to repeat an exact number of times.
     *
     * @param int $times How many times it repeats
     *
     * @example An exact quantifier has the same bound at both ends
     *     \App\Gql\Syntax\Pattern\Quantifier::exactly(3)->most // => 3
     *
     * @return self The quantifier
     */
    public static function exactly(int $times): self
    {
        return new self($times, $times);
    }

    /**
     * Reports whether a number of repetitions is within the bounds.
     *
     * @param int $times How many repetitions to test
     *
     * @example A repetition inside the bounds is allowed
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1, 3))->allows(2) // => true
     * @example One past the upper bound is not
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1, 3))->allows(4) // => false
     * @example Without an upper bound, nothing is too many
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1))->allows(99) // => true
     *
     * @return bool True when that many repetitions match
     */
    public function allows(int $times): bool
    {
        return $times >= $this->least && ($this->most === null || $times <= $this->most);
    }

    /**
     * Returns how far a search should go, given how far it is willing to.
     *
     * A repetition written without an upper bound goes as far as the graph allows,
     * which on a graph with cycles is only finite because a path mode makes it so —
     * and even then can be very large. The bound a search is willing to go to is
     * therefore used in place of the one that was not written, and an upper bound
     * that was written is honoured whatever it is: a reader who asks for twenty steps
     * has said what they want.
     *
     * @param int $willing How far the search is willing to go when nothing was written
     *
     * @example A written upper bound is honoured, however far it goes
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1, 20))->ceiling(10) // => 20
     * @example One that was not written is taken from what the search is willing to do
     *     (new \App\Gql\Syntax\Pattern\Quantifier(1))->ceiling(10) // => 10
     *
     * @return int The most repetitions the search should make
     */
    public function ceiling(int $willing): int
    {
        return $this->most ?? $willing;
    }
}
