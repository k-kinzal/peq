<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;

/**
 * The labels a symbol carries when a query looks at it.
 *
 * A GQL pattern selects by label, so what labels exist decides what questions are
 * easy to ask. Each symbol therefore carries three kinds at once: what it is —
 * `Method`, `Class` — what family it belongs to — `ClassLike`, `Member`, `Callable` —
 * and whether analysis actually found it.
 *
 * The families are the useful part. "Every callable that a controller reaches"
 * covers methods and functions in one pattern; "every class-like that is not an
 * interface" is `(:ClassLike&!Interface)` rather than a disjunction someone has to
 * remember to keep up to date. Without them, a query would have to enumerate kinds,
 * and would quietly stop being right when a kind was added.
 *
 * @visibility App\Gql
 */
final class NodeLabels
{
    /**
     * Returns the labels a symbol carries.
     *
     * @param Node $node The symbol
     *
     * @example A method belongs to two families at once
     *     $named = \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Invoice', 'total');
     *     \App\Gql\Element\NodeLabels::of(new \App\Analyzer\Graph\Node\MethodNode($named, true)) // => ['Method', 'Member', 'Callable', 'Resolved']
     * @example A symbol analysis never found says so
     *     $named = new \App\Analyzer\Graph\NodeId\UnknownNodeId('App\\Missing');
     *     \App\Gql\Element\NodeLabels::of(new \App\Analyzer\Graph\Node\UnknownNode($named)) // => ['Unknown', 'Unresolved']
     *
     * @return list<string> The labels, most specific first
     */
    public static function of(Node $node): array
    {
        return [...self::forKind($node->kind()), $node->resolved() ? 'Resolved' : 'Unresolved'];
    }

    /**
     * Returns the labels a kind of symbol carries, apart from whether it was found.
     *
     * @param NodeKind $kind The kind of symbol
     *
     * @example A class is a class-like
     *     \App\Gql\Element\NodeLabels::forKind(\App\Analyzer\Graph\NodeKind::Klass) // => ['Class', 'ClassLike']
     * @example A function is callable without being a member of anything
     *     \App\Gql\Element\NodeLabels::forKind(\App\Analyzer\Graph\NodeKind::Function) // => ['Function', 'Callable']
     *
     * @return list<string> The labels, most specific first
     */
    public static function forKind(NodeKind $kind): array
    {
        return match ($kind) {
            NodeKind::Klass => ['Class', 'ClassLike'],
            NodeKind::Interface => ['Interface', 'ClassLike'],
            NodeKind::Trait => ['Trait', 'ClassLike'],
            NodeKind::Enum => ['Enum', 'ClassLike'],
            NodeKind::Method => ['Method', 'Member', 'Callable'],
            NodeKind::Function => ['Function', 'Callable'],
            NodeKind::Closure => ['Closure', 'Callable'],
            NodeKind::Property => ['Property', 'Member'],
            NodeKind::Constant => ['Constant', 'Member'],
            NodeKind::EnumCase => ['EnumCase', 'Member'],
            NodeKind::Builtin => ['Builtin'],
            NodeKind::Unknown => ['Unknown'],
        };
    }

    /**
     * Returns every label a symbol can carry, for a reader asking what there is.
     *
     * An agent writing a query against a graph it has not seen needs to know what
     * the labels are before it can ask anything, and working them out by looking at
     * the answer to a query is a poor substitute for being told.
     *
     * @example The families are among the labels a query can ask for
     *     in_array('Callable', \App\Gql\Element\NodeLabels::all(), true) // => true
     *
     * @return list<string> Every label, without repetition
     */
    public static function all(): array
    {
        $labels = [];
        foreach (NodeKind::cases() as $kind) {
            foreach (self::forKind($kind) as $label) {
                $labels[$label] = true;
            }
        }

        return [...array_keys($labels), 'Resolved', 'Unresolved'];
    }
}
