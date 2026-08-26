<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use PHPUnit\Framework\Assert;

/**
 * Asks a graph whether it holds a relation, by the names its ends end with.
 *
 * A check about one construct does not care which namespace the sample source was
 * written in, only that the relation runs between the symbols it named. Matching on
 * the end of a name is what lets the sample stay a snippet, and reporting the whole
 * symbol list when nothing matched is what makes a failure readable.
 */
final class GraphRelations
{
    /**
     * Asserts that the graph holds a relation of that kind between those symbols.
     *
     * @param Graph    $graph      The graph to search
     * @param string   $fromSuffix The end of the name the relation starts at
     * @param string   $toSuffix   The end of the name it points at
     * @param EdgeKind $kind       The kind of relation
     * @param string   $message    What the check was about, when the names do not say it
     */
    public static function assertRelationExists(Graph $graph, string $fromSuffix, string $toSuffix, EdgeKind $kind, string $message = ''): void
    {
        Assert::assertTrue(
            self::hasRelation($graph, $fromSuffix, $toSuffix, $kind),
            sprintf(
                "%sNo %s relation runs from \"%s\" to \"%s\".\nThe graph holds: %s",
                $message === '' ? '' : $message."\n",
                $kind->value,
                $fromSuffix,
                $toSuffix,
                implode(', ', self::symbolNames($graph)),
            ),
        );
    }

    /**
     * Asserts that the graph holds no such relation.
     *
     * @param Graph    $graph      The graph to search
     * @param string   $fromSuffix The end of the name the relation would start at
     * @param string   $toSuffix   The end of the name it would point at
     * @param EdgeKind $kind       The kind of relation
     * @param string   $message    What the check was about, when the names do not say it
     */
    public static function assertRelationMissing(Graph $graph, string $fromSuffix, string $toSuffix, EdgeKind $kind, string $message = ''): void
    {
        Assert::assertFalse(
            self::hasRelation($graph, $fromSuffix, $toSuffix, $kind),
            sprintf(
                '%sA %s relation runs from "%s" to "%s", and none should.',
                $message === '' ? '' : $message."\n",
                $kind->value,
                $fromSuffix,
                $toSuffix,
            ),
        );
    }

    /**
     * Reports whether the graph holds a relation of that kind between those symbols.
     *
     * @param Graph    $graph      The graph to search
     * @param string   $fromSuffix The end of the name the relation starts at
     * @param string   $toSuffix   The end of the name it points at
     * @param EdgeKind $kind       The kind of relation
     *
     * @return bool True when the graph holds one
     */
    public static function hasRelation(Graph $graph, string $fromSuffix, string $toSuffix, EdgeKind $kind): bool
    {
        foreach ($graph->nodes() as $node) {
            if (!str_ends_with($node->id()->toString(), $fromSuffix)) {
                continue;
            }

            foreach ($graph->edges($node->id()) as $edge) {
                if ($edge->kind() === $kind && str_ends_with($edge->to()->toString(), $toSuffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Asserts how many relations of that kind run between those symbols.
     *
     * @param Graph    $graph      The graph to search
     * @param string   $fromSuffix The end of the name the relations start at
     * @param string   $toSuffix   The end of the name they point at
     * @param EdgeKind $kind       The kind of relation
     * @param int      $expected   How many of them the graph should hold
     */
    public static function assertRelationCount(Graph $graph, string $fromSuffix, string $toSuffix, EdgeKind $kind, int $expected): void
    {
        Assert::assertSame(
            $expected,
            self::countRelations($graph, $fromSuffix, $toSuffix, $kind),
            sprintf(
                "Expected %d %s relation(s) from \"%s\" to \"%s\".\nThe graph holds: %s",
                $expected,
                $kind->value,
                $fromSuffix,
                $toSuffix,
                implode(', ', self::symbolNames($graph)),
            ),
        );
    }

    /**
     * Counts the relations of that kind running between those symbols.
     *
     * @param Graph    $graph      The graph to search
     * @param string   $fromSuffix The end of the name the relations start at
     * @param string   $toSuffix   The end of the name they point at
     * @param EdgeKind $kind       The kind of relation
     *
     * @return int How many the graph holds
     */
    public static function countRelations(Graph $graph, string $fromSuffix, string $toSuffix, EdgeKind $kind): int
    {
        $count = 0;
        foreach ($graph->nodes() as $node) {
            if (!str_ends_with($node->id()->toString(), $fromSuffix)) {
                continue;
            }

            foreach ($graph->edges($node->id()) as $edge) {
                if ($edge->kind() === $kind && str_ends_with($edge->to()->toString(), $toSuffix)) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Describes every relation the graph holds, in both directions.
     *
     * @param Graph $graph The graph to read
     *
     * @return list<string> The relations, as "from--[kind]-->to"
     */
    public static function signatures(Graph $graph): array
    {
        $signatures = [];
        foreach ($graph->nodes() as $node) {
            foreach ($graph->edges($node->id()) as $edge) {
                $signatures[] = $edge->from()->toString().'--['.$edge->kind()->value.']-->'.$edge->to()->toString();
            }
        }

        return $signatures;
    }

    /**
     * Names every symbol the graph holds.
     *
     * @param Graph $graph The graph to read
     *
     * @return list<string> The names, in the order the graph holds them
     */
    public static function symbolNames(Graph $graph): array
    {
        return array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
    }
}
