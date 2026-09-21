<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Decides which of two descriptions of the same symbol the graph keeps.
 *
 * One symbol is met many times during an analysis, and the meetings are not equally
 * informative. A class is met where it is declared — with its file, its keywords, its
 * attributes — and met again as the owner of each of its methods, as the type of a
 * parameter, as the target of a `new`. Those later meetings know the name and nothing
 * more, and they arrive in whatever order the collectors happen to report them.
 *
 * Keeping the first description would therefore make what the graph knows about a
 * symbol depend on the order its files were read, which is not a property a graph
 * should have. Keeping the most informative one instead makes the result the same
 * whichever way the analysis walked, and it is what lets a declaration recorded by
 * one processor survive a bare reference recorded by another.
 */
final class NodePrecedence
{
    /**
     * How much a description says about the symbol, in facts a reference cannot know.
     *
     * The weights are ordered rather than counted: knowing what kind of symbol it is
     * outranks having been resolved, which outranks having read its declaration,
     * which outranks knowing where it is written. That order is the order in which
     * the facts stop being guesses — a placeholder knows none of them, and each later
     * one is only available to something that actually read the source.
     *
     * @param Node $node The description to weigh
     *
     * @example A symbol only ever referred to says the least about itself
     *     $named = \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice');
     *     \App\Analyzer\Graph\NodePrecedence::describes(\App\Analyzer\Graph\Node\UnknownNode::standingInFor($named)) // => 0
     * @example A symbol read where it is declared says the most
     *     $read = new \App\Analyzer\Graph\Node\ClassNode(
     *         \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice'),
     *         true,
     *         new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1),
     *         new \App\Analyzer\Graph\Declaration\SymbolDeclaration(
     *             modifiers: new \App\Analyzer\Graph\Declaration\Modifiers(final: true),
     *         ),
     *     );
     *     \App\Analyzer\Graph\NodePrecedence::describes($read) // => 15
     *
     * @return int The weight of the description, zero for one that says nothing
     */
    public static function describes(Node $node): int
    {
        $declared = $node->declaration();

        return ($node->kind() === NodeKind::Unknown ? 0 : 8)
            + ($node->resolved() ? 4 : 0)
            + ($declared !== null && !$declared->empty() ? 2 : 0)
            + ($node->meta() !== null ? 1 : 0);
    }

    /**
     * Reports whether a newly met description replaces the recorded one.
     *
     * Equal descriptions leave the recorded one in place. Two meetings that say the
     * same amount say the same thing, and preferring the later one would reintroduce
     * the order dependence this class exists to remove.
     *
     * @param Node $recorded The description the graph already holds
     * @param Node $offered  The description analysis has just met
     *
     * @example A declaration replaces the placeholder an edge left behind
     *     $named = \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice');
     *     $placeholder = \App\Analyzer\Graph\Node\UnknownNode::standingInFor($named);
     *     \App\Analyzer\Graph\NodePrecedence::prefers($placeholder, new \App\Analyzer\Graph\Node\ClassNode($named, true)) // => true
     * @example A bare reference does not replace a declaration
     *     $named = \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Domain\\Invoice');
     *     $declared = new \App\Analyzer\Graph\Node\ClassNode($named, true);
     *     \App\Analyzer\Graph\NodePrecedence::prefers($declared, new \App\Analyzer\Graph\Node\ClassNode($named, false)) // => false
     *
     * @return bool True when the offered description says strictly more
     */
    public static function prefers(Node $recorded, Node $offered): bool
    {
        return self::describes($offered) > self::describes($recorded);
    }
}
