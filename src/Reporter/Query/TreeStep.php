<?php

declare(strict_types=1);

namespace App\Reporter\Query;

/**
 * One step along a path, as a tree shows it.
 *
 * A path a query bound is a chain of relations and symbols, and a tree of paths is
 * those chains with their common beginnings drawn once. A step is therefore the pair
 * that makes a chain comparable: how the reader got here, and where here is.
 *
 * @visibility App\Reporter\Query
 */
final class TreeStep
{
    /**
     * @param string $label     What relation was followed to get here, empty at the start of a path
     * @param string $id        What identifies the symbol arrived at
     * @param bool   $backwards Whether the relation was followed against its direction, from where it points to where it starts
     */
    public function __construct(
        public readonly string $label,
        public readonly string $id,
        public readonly bool $backwards = false,
    ) {}

    /**
     * Returns what tells this step apart from any other.
     *
     * Two paths share a beginning when they took the same relations to the same
     * symbols, the same way round, which means every part of a step counts: a class
     * reached by being extended and by being called is reached two different ways, and
     * so is a method reached by calling it and by being called by it.
     *
     * @example A step is told apart by how it was reached and where it arrived
     *     (new \App\Reporter\Query\TreeStep('calls', 'App\\Money::add'))->key() // => "calls\0>\0App\\Money::add"
     * @example A relation followed backwards reaches it another way
     *     (new \App\Reporter\Query\TreeStep('calls', 'App\\Money::add', true))->key() // => "calls\0<\0App\\Money::add"
     *
     * @return string What identifies the step
     */
    public function key(): string
    {
        return $this->label."\0".($this->backwards ? '<' : '>')."\0".$this->id;
    }
}
