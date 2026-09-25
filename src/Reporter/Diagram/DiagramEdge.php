<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

/**
 * One relation as a drawing shows it.
 *
 * A relation is drawn as an arrow from one symbol to another, named by what it is.
 * Both ends are identities rather than symbols, so the drawing can be built up in any
 * order and can leave out an arrow whose far end it never reached.
 */
final readonly class DiagramEdge
{
    /**
     * @param string $origin What identifies the symbol the arrow leaves
     * @param string $target What identifies the symbol it arrives at
     * @param string $label  What the relation is
     */
    public function __construct(
        public string $origin,
        public string $target,
        public string $label = '',
        public bool $detailed = false,
    ) {}

    /**
     * Returns what tells this relation apart from every other in the drawing.
     *
     * Two symbols may be related more than once — a method that both calls another
     * and takes its type as a parameter — and a drawing should show both arrows. What
     * it should not show is the same arrow twice, which happens whenever a walk
     * reaches the same pair from two directions.
     *
     * @example A relation is told apart by its two ends and what it is
     *     (new \App\Reporter\Diagram\DiagramEdge('a', 'b', 'calls'))->signature() // => 'a|calls|b'
     *
     * @return string What identifies the relation in the drawing
     */
    public function signature(): string
    {
        return $this->origin.'|'.$this->label.'|'.$this->target;
    }
}
