<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Result\ResultTable;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes the paths a query bound as one tree.
 *
 * Paths are how a query answers "how does this reach that", and a list of them
 * repeats their shared beginnings once per path — which, for a controller that
 * reaches forty things through the same two calls, is most of the answer. Drawing
 * them as a tree writes each shared beginning once, which is the same reason peq's
 * own inspection draws a tree.
 *
 * The difference is what decides the shape. The inspection walks whatever the graph
 * holds; this draws whatever the query chose to bind, so the tree is of the reader's
 * own question rather than of the whole graph.
 *
 * A query that binds no path writes nothing here, and its answer is a table. Binding
 * one is a deliberate act — `MATCH p = (a)-[:call]->{1,4}(b)` — and a reader who did
 * not do it did not ask for a tree.
 *
 * @visibility App\Reporter
 */
final class TreeWriter implements QueryReporter
{
    /**
     * Writes the paths the answer holds as a tree.
     *
     * @param ResultTable     $result What the query answered
     * @param OutputInterface $output Where it is written
     */
    #[Override]
    public function report(ResultTable $result, OutputInterface $output): void
    {
        $paths = self::paths($result);
        if ($paths === []) {
            return;
        }

        $previous = [];
        $continues = [];
        foreach ($paths as $place => $path) {
            $shared = self::sharedLength($previous, $path);
            for ($depth = $shared; $depth < count($path); ++$depth) {
                $continues[$depth] = self::hasSibling($paths, $place, $depth);
                $output->writeln(self::line($path, $depth, $continues), OutputInterface::OUTPUT_RAW);
            }
            $previous = $path;
        }
    }

    /**
     * Returns the paths an answer holds, in the order a tree draws them.
     *
     * They are sorted so that paths sharing a beginning stand together, which is what
     * lets the tree be drawn in one pass without building one.
     *
     * @param ResultTable $result What the query answered
     *
     * @example An answer that binds no path holds no paths
     *     \App\Reporter\Query\TreeWriter::paths(\App\Gql\Result\ResultTable::nothing()) // => []
     *
     * @return list<list<TreeStep>> The paths, sorted and without repetition
     */
    public static function paths(ResultTable $result): array
    {
        $found = [];
        foreach ($result->rows as $row) {
            foreach (ResultElements::flattened($row->values) as $value) {
                if ($value instanceof PathDatum) {
                    $steps = self::steps($value);
                    $found[self::signature($steps)] = $steps;
                }
            }
        }
        ksort($found);

        return array_values($found);
    }

    /**
     * Returns one path as the steps a tree draws it in.
     *
     * @param PathDatum $path The path
     *
     * @example A path that crosses nothing is one step
     *     count(\App\Reporter\Query\TreeWriter::steps(\App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a')))) // => 1
     * @example A path that crosses one relation is two
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     count(\App\Reporter\Query\TreeWriter::steps($path)) // => 2
     * @example A relation crossed against its direction is a step taken backwards
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('b'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('a'));
     *     \App\Reporter\Query\TreeWriter::steps($path)[1]->backwards // => true
     *
     * @return list<TreeStep> The steps, beginning where the path begins
     */
    public static function steps(PathDatum $path): array
    {
        $steps = [];
        $crossed = null;
        foreach ($path->elements as $element) {
            if ($element instanceof EdgeDatum) {
                $crossed = $element;

                continue;
            }
            if ($element instanceof NodeDatum) {
                $steps[] = $crossed === null
                    ? new TreeStep('', $element->id)
                    : new TreeStep($crossed->label(), $element->id, $crossed->origin === $element->id && $crossed->target !== $element->id);
                $crossed = null;
            }
        }

        return $steps;
    }

    /**
     * Returns what tells one path apart from another, and sorts them sensibly.
     *
     * @param list<TreeStep> $steps The steps of the path
     *
     * @example A path with no steps has nothing to tell it apart
     *     \App\Reporter\Query\TreeWriter::signature([]) // => ''
     *
     * @return string What identifies the path
     */
    public static function signature(array $steps): string
    {
        return implode("\1", array_map(static fn (TreeStep $step): string => $step->key(), $steps));
    }

    /**
     * Returns how many steps two paths begin with in common.
     *
     * @param list<TreeStep> $left  The path drawn before
     * @param list<TreeStep> $right The path being drawn
     *
     * @example A path shares nothing with nothing
     *     \App\Reporter\Query\TreeWriter::sharedLength([], [new \App\Reporter\Query\TreeStep('', 'a')]) // => 0
     * @example Two paths from the same symbol share their first step
     *     $left = [new \App\Reporter\Query\TreeStep('', 'a'), new \App\Reporter\Query\TreeStep('calls', 'b')];
     *     $right = [new \App\Reporter\Query\TreeStep('', 'a'), new \App\Reporter\Query\TreeStep('calls', 'c')];
     *     \App\Reporter\Query\TreeWriter::sharedLength($left, $right) // => 1
     *
     * @return int How many steps they share
     */
    public static function sharedLength(array $left, array $right): int
    {
        $shared = 0;
        $shortest = min(count($left), count($right));
        while ($shared < $shortest && $left[$shared]->key() === $right[$shared]->key()) {
            ++$shared;
        }

        return $shared;
    }

    /**
     * Reports whether another path branches off beside this one at a depth.
     *
     * A branch is drawn as closed or open depending on whether anything follows it at
     * the same depth, and what follows it is a later path that shares everything above
     * that depth and differs at it.
     *
     * @param list<list<TreeStep>> $paths The paths, sorted
     * @param int                  $place Which path is being drawn
     * @param int                  $depth Which step of it
     *
     * @example The only path there is has nothing beside it
     *     $paths = [[new \App\Reporter\Query\TreeStep('', 'a')]];
     *     \App\Reporter\Query\TreeWriter::hasSibling($paths, 0, 0) // => false
     * @example A path that differs at a depth stands beside the one before it
     *     $first = [new \App\Reporter\Query\TreeStep('', 'a'), new \App\Reporter\Query\TreeStep('calls', 'b')];
     *     $second = [new \App\Reporter\Query\TreeStep('', 'a'), new \App\Reporter\Query\TreeStep('calls', 'c')];
     *     \App\Reporter\Query\TreeWriter::hasSibling([$first, $second], 0, 1) // => true
     *
     * @return bool True when a later path branches off at that depth
     */
    public static function hasSibling(array $paths, int $place, int $depth): bool
    {
        for ($later = $place + 1; $later < count($paths); ++$later) {
            $shared = self::sharedLength($paths[$place], $paths[$later]);
            if ($shared === $depth) {
                return true;
            }
            if ($shared < $depth) {
                return false;
            }
        }

        return false;
    }

    /**
     * Writes one step of one path.
     *
     * @param list<TreeStep>   $path      The path
     * @param int              $depth     Which step of it
     * @param array<int, bool> $continues Whether the branch at each depth has more beside it
     *
     * @example The start of a path carries no branch drawing
     *     \App\Reporter\Query\TreeWriter::line([new \App\Reporter\Query\TreeStep('', 'a')], 0, []) // => 'a'
     * @example A step is drawn as the relation that reached it
     *     $path = [new \App\Reporter\Query\TreeStep('', 'a'), new \App\Reporter\Query\TreeStep('calls', 'b')];
     *     \App\Reporter\Query\TreeWriter::line($path, 1, [1 => false]) // => '└── calls ──> b'
     * @example A relation followed backwards points back, so that `a` is read as what `b` calls
     *     $path = [new \App\Reporter\Query\TreeStep('', 'a'), new \App\Reporter\Query\TreeStep('calls', 'b', true)];
     *     \App\Reporter\Query\TreeWriter::line($path, 1, [1 => false]) // => '└── calls <── b'
     *
     * @return string The line, without a trailing newline
     */
    public static function line(array $path, int $depth, array $continues): string
    {
        $step = $path[$depth];
        if ($depth === 0) {
            return $step->id;
        }

        $line = '';
        for ($above = 1; $above < $depth; ++$above) {
            $line .= ($continues[$above] ?? false) ? '│   ' : '    ';
        }

        return $line.(($continues[$depth] ?? false) ? '├── ' : '└── ').$step->label.($step->backwards ? ' <── ' : ' ──> ').$step->id;
    }
}
