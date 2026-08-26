<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use PhpParser\Node;

/**
 * Written types, positioned as the parser would have positioned them.
 *
 * A syntax node built by hand reports no position, and a relation drawn from it has
 * to say where it was written. Stamping a line onto the node is what lets a test
 * build a type by hand and still get a usable source location out of it.
 */
final class TypeReferences
{
    /**
     * Positions a syntax node as though it had been read from a file.
     *
     * @template TNode of Node
     *
     * @param int   $line The line the node should report itself as written on
     * @param TNode $node The node to position
     *
     * @return TNode The same node, positioned
     */
    public static function at(int $line, Node $node): Node
    {
        $node->setAttribute('startLine', $line);

        return $node;
    }
}
