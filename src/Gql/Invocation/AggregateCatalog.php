<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Which summaries a query can ask for, and how to start one.
 *
 * Aggregates are told apart from ordinary functions by name, before anything is
 * evaluated, because the difference decides how a whole projection is computed: a
 * projection holding one aggregate summarises its rows, and one holding none does
 * not. Asking that question of a name is what lets `RETURN count(*)` produce one row
 * without the query having to say `GROUP BY` anything.
 *
 * @visibility App\Gql
 */
final class AggregateCatalog
{
    /**
     * The summaries GQL defines.
     */
    private const NAMES = ['count', 'sum', 'avg', 'min', 'max', 'collect_list'];

    /**
     * Reports whether a name is one of the summaries.
     *
     * @param string $name The function name, as the query wrote it
     *
     * @example A summary is recognised however it is cased
     *     \App\Gql\Invocation\AggregateCatalog::isAggregate('COUNT') // => true
     * @example An ordinary function is not one
     *     \App\Gql\Invocation\AggregateCatalog::isAggregate('upper') // => false
     *
     * @return bool True when it is a summary
     */
    public static function isAggregate(string $name): bool
    {
        return in_array(strtolower($name), self::NAMES, true);
    }

    /**
     * Starts a summary.
     *
     * @param string $name     The function name, as the query wrote it
     * @param bool   $distinct Whether repeated values are dropped before summarising
     * @param bool   $rows     Whether the call was written over rows rather than over a value
     *
     * @example A summary is started by the name a query asks for it by
     *     \App\Gql\Invocation\AggregateCatalog::start('avg', false, false)->result()->kind() // => \App\Gql\Datum\DatumKind::Null
     * @example Asking to drop repeats wraps whatever summary was asked for
     *     \App\Gql\Invocation\AggregateCatalog::start('count', true, false) instanceof \App\Gql\Invocation\DistinctAccumulator // => true
     *
     * @return Accumulator The summary, ready to be offered values
     *
     * @throws GqlException If no summary goes by that name
     */
    public static function start(string $name, bool $distinct, bool $rows): Accumulator
    {
        $summary = match (strtolower($name)) {
            'count' => new CountAccumulator($rows),
            'sum' => new SumAccumulator(),
            'avg' => new AverageAccumulator(),
            'min' => new ExtremeAccumulator(),
            'max' => new ExtremeAccumulator(true),
            'collect_list' => new CollectAccumulator(),
            default => throw GqlException::because(
                StatusCode::UnknownFeature,
                sprintf('there is no aggregate function called "%s"', $name),
            ),
        };

        return $distinct ? new DistinctAccumulator($summary) : $summary;
    }

    /**
     * Returns the names of every summary, for a reader asking what there is.
     *
     * @example Counting is among them
     *     in_array('count', \App\Gql\Invocation\AggregateCatalog::all(), true) // => true
     *
     * @return list<string> The names
     */
    public static function all(): array
    {
        return self::NAMES;
    }
}
