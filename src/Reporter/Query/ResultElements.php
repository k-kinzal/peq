<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\PathDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\ElementGraph;
use App\Gql\Result\ResultRow;
use App\Gql\Result\ResultTable;
use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;

/**
 * The piece of graph a query's answer holds.
 *
 * A result is a table, and a table of symbols and relations is also a graph — the one
 * the query narrowed down to. Reading it back out is what lets the same answer be
 * drawn as a picture as well as listed, and it is why a query that binds a path is
 * worth writing: the path carries the relations, and the relations are what a drawing
 * is made of.
 *
 * Symbols are collected before relations, so a symbol the answer describes in full is
 * drawn in full rather than as the bare identity an arrow knows it by.
 *
 * @visibility App\Reporter
 */
final class ResultElements
{
    /**
     * Returns the drawing of the piece of graph an answer holds.
     *
     * A query usually binds the symbols it was written about and not the relations
     * between them — `(c)-[:declaresMethod]->(m)` binds two symbols and no relation —
     * so the graph is read again for every relation between the symbols that were
     * found. Without that, `--output=graph` after a perfectly ordinary query would
     * draw a list of boxes and no arrows.
     *
     * @param ResultTable       $result What the query answered
     * @param null|ElementGraph $graph  The graph it was answered from, or null when there is none to read
     *
     * @example An answer holding nothing draws nothing
     *     \App\Reporter\Query\ResultElements::of(\App\Gql\Result\ResultTable::nothing())->empty() // => true
     * @example An answer holding a symbol draws it
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('p', new \App\Gql\Datum\NodeDatum('App\\Invoice', ['Class']));
     *     $result = \App\Gql\Result\ResultTable::of(['p'], new \App\Gql\Binding\BindingTable([$row]));
     *     count(\App\Reporter\Query\ResultElements::of($result)->nodes()) // => 1
     *
     * @return Diagram The drawing
     */
    public static function of(ResultTable $result, ?ElementGraph $graph = null): Diagram
    {
        $diagram = new Diagram();
        foreach ($result->rows as $row) {
            self::collectNodes($diagram, $row);
        }
        foreach ($result->rows as $row) {
            self::collectRelations($diagram, $row);
        }
        if ($graph !== null) {
            self::connect($diagram, $graph);
        }

        return $diagram;
    }

    /**
     * Records every relation the graph holds between two symbols already drawn.
     *
     * @param Diagram      $diagram The drawing being built
     * @param ElementGraph $graph   The graph the answer came from
     *
     * @example A drawing with nothing in it gains nothing
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     \App\Reporter\Query\ResultElements::connect($diagram, new \App\Gql\Element\ElementGraph([], [], []));
     *     $diagram->edges() // => []
     */
    public static function connect(Diagram $diagram, ElementGraph $graph): void
    {
        $drawn = [];
        foreach ($diagram->nodes() as $node) {
            $drawn[$node->id] = true;
        }

        foreach ($graph->between($drawn) as $edge) {
            $diagram->relate(new DiagramEdge($edge->origin, $edge->target, EdgeCaption::of($edge), $edge->property('callSite') instanceof StringDatum));
        }
    }

    /**
     * Records every symbol one row of an answer holds.
     *
     * @param Diagram   $diagram The drawing being built
     * @param ResultRow $row     The row
     *
     * @example A row holding no symbol adds nothing to the drawing
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     \App\Reporter\Query\ResultElements::collectNodes($diagram, new \App\Gql\Result\ResultRow([new \App\Gql\Datum\IntegerDatum(1)]));
     *     $diagram->empty() // => true
     */
    public static function collectNodes(Diagram $diagram, ResultRow $row): void
    {
        foreach (self::flattened($row->values) as $value) {
            if ($value instanceof NodeDatum) {
                $diagram->add(self::drawn($value));
            }
        }
    }

    /**
     * Records every relation one row of an answer holds, and the symbols it joins.
     *
     * @param Diagram   $diagram The drawing being built
     * @param ResultRow $row     The row
     *
     * @example A relation brings the symbols it joins into the drawing with it
     *     $diagram = new \App\Reporter\Diagram\Diagram();
     *     $edge = new \App\Gql\Datum\EdgeDatum('e', ['calls'], [], 'a', 'b');
     *     \App\Reporter\Query\ResultElements::collectRelations($diagram, new \App\Gql\Result\ResultRow([$edge]));
     *     count($diagram->nodes()) // => 2
     */
    public static function collectRelations(Diagram $diagram, ResultRow $row): void
    {
        foreach (self::flattened($row->values) as $value) {
            if (!$value instanceof EdgeDatum) {
                continue;
            }
            $diagram->add(new DiagramNode($value->origin));
            $diagram->add(new DiagramNode($value->target));
            $diagram->relate(new DiagramEdge($value->origin, $value->target, EdgeCaption::of($value), $value->property('callSite') instanceof StringDatum));
        }
    }

    /**
     * Returns every value a row holds, looking inside lists and paths.
     *
     * A path is the reason this exists: it is one column holding a whole chain of
     * symbols and relations, and a drawing that only looked at columns would see one
     * value where the answer holds a dozen.
     *
     * @param list<Datum> $values The values
     *
     * @example A path is read as everything it is made of
     *     $path = \App\Gql\Datum\PathDatum::at(new \App\Gql\Datum\NodeDatum('a'))->continuedBy(new \App\Gql\Datum\EdgeDatum('e', [], [], 'a', 'b'), new \App\Gql\Datum\NodeDatum('b'));
     *     count(\App\Reporter\Query\ResultElements::flattened([$path])) // => 4
     *
     * @return list<Datum> The values, with the contents of lists and paths beside them
     */
    public static function flattened(array $values): array
    {
        $found = [];
        foreach ($values as $value) {
            $found[] = $value;
            if ($value instanceof ListDatum) {
                array_push($found, ...self::flattened($value->items));
            }
            if ($value instanceof PathDatum) {
                array_push($found, ...self::flattened($value->elements));
            }
        }

        return $found;
    }

    /**
     * Returns one symbol as a drawing shows it.
     *
     * @param NodeDatum $node The symbol
     *
     * @example A symbol is drawn with what it is and where it is written
     *     $node = new \App\Gql\Datum\NodeDatum('App\\Invoice', ['Class'], [
     *         'kind' => new \App\Gql\Datum\StringDatum('class'),
     *         'file' => new \App\Gql\Datum\StringDatum('src/Invoice.php'),
     *         'line' => new \App\Gql\Datum\IntegerDatum(12),
     *     ]);
     *     \App\Reporter\Query\ResultElements::drawn($node)->location // => 'src/Invoice.php:12'
     *
     * @return DiagramNode The symbol, as a drawing shows it
     */
    public static function drawn(NodeDatum $node): DiagramNode
    {
        $file = $node->property('file');
        $line = $node->property('line');
        $location = $file->kind() === DatumKind::Text && $line->kind() === DatumKind::Integer
            ? $file->toText().':'.$line->toText()
            : null;

        $kind = $node->property('kind');

        return new DiagramNode($node->id, $kind->kind() === DatumKind::Text ? $kind->toText() : '', $location);
    }
}
