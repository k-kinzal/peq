<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\Datum;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;

/**
 * How far one attempt at matching a pattern has got.
 *
 * Matching a graph pattern is a search, and a search needs to remember four things at
 * once: what it has bound so far, where in the graph it is standing, the path it took
 * to get there, and what it has already been through. The last two are not the same:
 * the path is the answer a query can ask for, and what has been been through is what
 * keeps the search finite.
 *
 * Every step produces a new state rather than changing this one, because a search
 * that finds three ways forward has to carry on down all three from the same place.
 *
 * @visibility App\Gql
 */
final readonly class MatchState
{
    /**
     * @param BindingRow          $row     What the attempt has bound so far
     * @param null|NodeDatum      $current Where in the graph it is standing, or null before it has started
     * @param null|PathDatum      $path    The path it took to get there, or null before it has started
     * @param array<string, true> $edges   The relations it has crossed, by what identifies them
     * @param array<string, true> $nodes   The symbols it has met, by what identifies them
     * @param null|string         $start   What identifies the symbol it started from, or null before it has started
     */
    public function __construct(
        public BindingRow $row,
        public ?NodeDatum $current = null,
        public ?PathDatum $path = null,
        private array $edges = [],
        private array $nodes = [],
        private ?string $start = null,
    ) {}

    /**
     * Returns the attempt as it stands before it has gone anywhere.
     *
     * @param BindingRow $row What is already bound when the pattern is reached
     *
     * @example An attempt that has not started stands nowhere
     *     \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->current // => null
     *
     * @return self The attempt
     */
    public static function before(BindingRow $row): self
    {
        return new self($row);
    }

    /**
     * Returns the attempt standing at a symbol it has just started from.
     *
     * @param NodeDatum $node The symbol
     *
     * @example Starting somewhere begins a path there
     *     $started = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     $started->path?->length() // => 0
     *
     * @return self The attempt, standing there
     */
    public function startingAt(NodeDatum $node): self
    {
        return new self($this->row, $node, PathDatum::at($node), $this->edges, [$node->id => true], $node->id);
    }

    /**
     * Returns the attempt having crossed a relation to another symbol.
     *
     * @param EdgeDatum $edge The relation crossed
     * @param NodeDatum $node The symbol arrived at
     *
     * @example Crossing a relation makes the path one longer
     *     $started = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     $crossed = $started->across(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     $crossed->path?->length() // => 1
     *
     * @return self The attempt, standing at the other symbol
     */
    public function across(EdgeDatum $edge, NodeDatum $node): self
    {
        $path = $this->path ?? PathDatum::at($node);

        return new self(
            $this->row,
            $node,
            $this->path === null ? $path : $path->continuedBy($edge, $node),
            [...$this->edges, $edge->id => true],
            [...$this->nodes, $node->id => true],
            $this->start ?? $node->id,
        );
    }

    /**
     * Returns the attempt with what it has bound replaced, and everything else about it kept.
     *
     * A repeated group binds its names afresh on every repetition, so each repetition
     * is matched against a row without them and the row is put back together after.
     *
     * @param BindingRow $row What the attempt has bound
     *
     * @example The path an attempt has walked is kept
     *     $state = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     $state->withRow(\App\Gql\Binding\BindingRow::unit())->current?->id // => 'a'
     *
     * @return self The attempt, with that row
     */
    public function withRow(BindingRow $row): self
    {
        return new self($row, $this->current, $this->path, $this->edges, $this->nodes, $this->start);
    }

    /**
     * Returns the attempt with one more name bound.
     *
     * @param string $name  The name
     * @param Datum  $value What to bind it to
     *
     * @example Binding a name leaves everything else about the attempt alone
     *     $before = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit());
     *     $before->bind('n', new \App\Gql\Datum\IntegerDatum(1))->current // => null
     *
     * @return self The attempt, with that binding
     */
    public function bind(string $name, Datum $value): self
    {
        return new self($this->row->with($name, $value), $this->current, $this->path, $this->edges, $this->nodes, $this->start);
    }

    /**
     * Reports whether the attempt has already crossed a relation.
     *
     * @param string $id What identifies the relation
     *
     * @example An attempt that has gone nowhere has crossed nothing
     *     \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->crossed('e') // => false
     *
     * @return bool True when it has
     */
    public function crossed(string $id): bool
    {
        return isset($this->edges[$id]);
    }

    /**
     * Reports whether the attempt has already met a symbol.
     *
     * @param string $id What identifies the symbol
     *
     * @example An attempt has met the symbol it started from
     *     $started = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     $started->met('a') // => true
     *
     * @return bool True when it has
     */
    public function met(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * Reports whether a symbol is the one the attempt started from.
     *
     * A path that comes back to where it started is a cycle, and one mode allows
     * exactly that and nothing else repeated, which is why this is asked separately
     * from whether a symbol has been met.
     *
     * @param string $id What identifies the symbol
     *
     * @example The symbol an attempt started from is where it started
     *     $started = \App\Gql\Matching\MatchState::before(\App\Gql\Binding\BindingRow::unit())->startingAt(new \App\Gql\Datum\NodeDatum('a'));
     *     $started->startedAt('a') // => true
     *
     * @return bool True when it is
     */
    public function startedAt(string $id): bool
    {
        return $this->start === $id;
    }
}
