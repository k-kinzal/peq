<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\AttributeEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PossibleCallEdge;
use App\Gql\Datum\Datum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\StringDatum;

/**
 * What a query can ask a relation about itself.
 *
 * A relation knows less than a symbol does, and what it knows is mostly where it is
 * written. That turns out to be the most useful thing about it: a query that finds
 * which methods reach a cache wants to end by saying which lines to go and look at,
 * and those lines are the relations' rather than the symbols'.
 *
 * @visibility App\Gql
 */
final class EdgeProperties
{
    /**
     * Returns everything a query can ask a relation.
     *
     * @param Edge $edge The relation
     *
     * @example A relation knows what kind it is and where it is written
     *     $meta = new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1);
     *     $caller = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Invoice', 'total'), true);
     *     $called = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Money', 'add'), true);
     *     $written = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $called, $meta);
     *     \App\Gql\Element\EdgeProperties::of($written)['line']->toText() // => '12'
     *
     * @return array<string, Datum> The properties, by name
     */
    public static function of(Edge $edge): array
    {
        $meta = $edge->meta();

        return [
            'kind' => new StringDatum($edge->kind()->value),
            'file' => new StringDatum($meta->path),
            'fileName' => new StringDatum($meta->name),
            'line' => new IntegerDatum($meta->line),
            'column' => new IntegerDatum($meta->column),
            ...($meta->offset === null ? [] : ['offset' => new IntegerDatum($meta->offset)]),
            ...self::evidence($edge),
        ];
    }

    /**
     * Exposes source occurrences and the evidence behind inferred targets.
     *
     * @return array<string, Datum> Queryable facts specific to this relation
     */
    public static function evidence(Edge $edge): array
    {
        if ($edge instanceof AttributeEdge) {
            return [
                'arguments' => new ListDatum(array_map(static fn (string $argument): Datum => new StringDatum($argument), $edge->arguments)),
                ...($edge->parameter === null ? [] : ['parameter' => new StringDatum($edge->parameter)]),
            ];
        }
        if ($edge instanceof MethodCallEdge && $edge->expression !== null) {
            return ['resolution' => new StringDatum('unresolved'), 'expression' => new StringDatum($edge->expression)];
        }
        if ($edge instanceof PossibleCallEdge) {
            return [
                'resolution' => new StringDatum('possible'),
                'receiverType' => new StringDatum($edge->receiverType),
                'declaredTarget' => new StringDatum($edge->call->to()->toString()),
                'basis' => new StringDatum('class-hierarchy'),
                ...($edge->implementationType === null ? [] : ['implementationType' => new StringDatum($edge->implementationType)]),
            ];
        }
        if ($edge instanceof MethodCallEdge) {
            return [
                'resolution' => new StringDatum('declared'),
                'declaredTarget' => new StringDatum($edge->to()->toString()),
                ...($edge->receiverType === null ? [] : ['receiverType' => new StringDatum($edge->receiverType)]),
            ];
        }

        return [];
    }

    /**
     * Returns every property a relation carries and what kind of value it is.
     *
     * An agent writing its first query against a graph it has not seen needs the
     * names before it can ask anything and the types before it can compare anything,
     * so both are offered rather than left to be discovered from an answer.
     *
     * @example Where a relation is written is among them, with the type it is
     *     \App\Gql\Element\EdgeProperties::all()['line'] // => 'INT64'
     *
     * @return array<string, string> The property names, and the GQL type of each
     */
    public static function all(): array
    {
        return [
            'kind' => 'STRING',
            'file' => 'STRING',
            'fileName' => 'STRING',
            'line' => 'INT64',
            'column' => 'INT64',
            'offset' => 'INT64',
            'resolution' => 'STRING',
            'receiverType' => 'STRING',
            'declaredTarget' => 'STRING',
            'basis' => 'STRING',
            'implementationType' => 'STRING',
            'expression' => 'STRING',
            'arguments' => 'LIST<STRING>',
            'parameter' => 'STRING',
        ];
    }
}
