<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

/**
 * One symbol as a drawing shows it.
 *
 * Deliberately not a graph node. Two very different things are drawn as diagrams —
 * the walk an inspection made, and the elements a query found — and the drawing
 * should not have to know which. What it needs is what a reader needs: what the
 * symbol is called, what kind of thing it is, and where to go and look at it.
 */
final class DiagramNode
{
    /**
     * @param string      $id       What identifies the symbol, unique in the drawing
     * @param string      $kind     What kind of symbol it is
     * @param null|string $location Where it is written, or null when that is not known
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind = '',
        public readonly ?string $location = null,
    ) {
        assert($this->id !== '', 'A drawn symbol is identified');
    }
}
