<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;

/**
 * The graph peq builds from one of the sample sources, described as plain text.
 *
 * Asserting the complete output of an analysis is what pins the processors down:
 * a check that one relation is present says nothing about a relation that should not
 * be there, or about one that quietly disappeared. Reading the whole result back as
 * sorted text makes the complete answer something a test can state in one place.
 */
final class AnalysedFixture
{
    /**
     * The key the graph of the whole sample tree is kept under.
     *
     * A fixture is named by a file name, and no file is named with a separator, so
     * this cannot collide with one.
     */
    private const WHOLE_TREE = '/';

    /**
     * @var array<string, Graph> The graphs already built, keyed by fixture name
     */
    private static array $graphs = [];

    /**
     * Returns the graph of one sample source, analysing it once per process.
     *
     * @param string $fixture The file name under tests/Fixture/Source, without its extension
     *
     * @return Graph The graph of that source
     */
    public static function graph(string $fixture): Graph
    {
        return self::$graphs[$fixture] ??= (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 2).'/Fixture/Source/'.$fixture.'.php')
        ;
    }

    /**
     * Returns the graph of every sample source at once, analysing them once per process.
     *
     * The sample sources are written between them to use every construct peq claims to
     * read, which a real source tree only does by accident: peq's own code contains
     * whichever relations its design happens to call for, and stops containing one the
     * moment a class is refactored. A check that must meet every kind of relation
     * therefore reads this tree rather than a real one.
     *
     * @return Graph The graph of everything under tests/Fixture/Source
     */
    public static function sourceTree(): Graph
    {
        return self::$graphs[self::WHOLE_TREE] ??= (new PhpStanAnalyzer())
            ->analyze(dirname(__DIR__, 2).'/Fixture/Source')
        ;
    }

    /**
     * Returns the name of every symbol the analysis found, sorted.
     *
     * @param string $fixture The file name under tests/Fixture/Source, without its extension
     *
     * @return list<string> The symbol names
     */
    public static function symbols(string $fixture): array
    {
        $names = array_map(
            static fn (\App\Analyzer\Graph\Node $node): string => $node->id()->toString(),
            self::graph($fixture)->nodes(),
        );
        sort($names);

        return $names;
    }

    /**
     * Returns every relation the analysis wrote, as "from -[kind]-> to", sorted.
     *
     * Only the relations source code actually writes are listed: the opposite
     * readings are derived from them, so listing both would say the same thing twice.
     *
     * @param string $fixture The file name under tests/Fixture/Source, without its extension
     *
     * @return list<string> The relations
     */
    public static function relations(string $fixture): array
    {
        $described = array_map(
            static fn (\App\Analyzer\Graph\Edge $edge): string => sprintf(
                '%s -[%s]-> %s',
                $edge->from()->toString(),
                $edge->kind()->value,
                $edge->to()->toString(),
            ),
            self::graph($fixture)->authoredEdges(),
        );
        sort($described);

        return $described;
    }
}
