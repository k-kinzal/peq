<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\Datum;
use App\Gql\Element\ElementGraph;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\GqlException;
use App\Gql\Matching\PatternMatching;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\Syntax\Pattern\GraphPattern;

/**
 * A pattern matched against the sample codebase, with what it bound written out.
 *
 * A match produces rows of bindings, and a test that asserted over those objects would
 * be unreadable long before it was wrong. Written out, a match reads
 * `a=Controller::show b=Invoice::total`, one line per way the pattern matched, sorted
 * so that the order the search happened to find them in never makes a test flap.
 *
 * The namespaces are dropped from what is written, because every symbol of the sample
 * codebase is under `App` and repeating that in an expectation hides the part that
 * differs.
 */
final class MatchedPattern
{
    /**
     * Matches a pattern against the sample codebase and writes what it bound out.
     *
     * @param string               $written  The pattern, as a query would write it
     * @param array<string, Datum> $bound    What is already bound when the pattern is reached
     * @param int                  $hopLimit How far a repetition goes when no upper bound was written
     * @param null|ElementGraph    $graph    The graph to match against, or null for the sample codebase
     *
     * @return string One line per way the pattern matched, in a settled order
     *
     * @throws GqlException If the pattern cannot be read or cannot be matched
     */
    public static function of(string $written, array $bound = [], int $hopLimit = 10, ?ElementGraph $graph = null): string
    {
        $lines = array_map(
            static fn (BindingRow $row): string => implode(' ', array_map(
                static fn (string $name): string => $name.'='.self::shortly($row->value($name)->toText()),
                $row->names(),
            )),
            self::rows($written, $bound, $hopLimit, $graph),
        );
        sort($lines);

        return implode('; ', $lines);
    }

    /**
     * Writes a value out without the namespaces every symbol of the sample shares.
     *
     * @param string $written The value, as it writes itself
     *
     * @return string The value, without its namespaces
     */
    public static function shortly(string $written): string
    {
        return str_replace(['App\Http\\', 'App\Domain\\', 'App\Cache\\', 'App\\'], '', $written);
    }

    /**
     * Matches a pattern against the sample codebase.
     *
     * @param string               $written  The pattern, as a query would write it
     * @param array<string, Datum> $bound    What is already bound when the pattern is reached
     * @param int                  $hopLimit How far a repetition goes when no upper bound was written
     * @param null|ElementGraph    $graph    The graph to match against, or null for the sample codebase
     *
     * @return list<BindingRow> One row per way the pattern matched
     *
     * @throws GqlException If the pattern cannot be read or cannot be matched
     */
    public static function rows(string $written, array $bound = [], int $hopLimit = 10, ?ElementGraph $graph = null): array
    {
        $matching = new PatternMatching($graph ?? self::graph(), new ExpressionEvaluation(), $hopLimit);

        return $matching->match(self::pattern($written), BindingRow::unit()->withAll($bound));
    }

    /**
     * Reads a pattern written the way a query writes one.
     *
     * @param string $written The pattern
     *
     * @return GraphPattern The pattern
     *
     * @throws GqlException If what is written is not a pattern
     */
    public static function pattern(string $written): GraphPattern
    {
        $tokens = TokenReader::of($written);

        return (new PatternParser($tokens, new ExpressionParser($tokens)))->parseGraph();
    }

    /**
     * Returns the sample codebase as a query sees it, read once.
     *
     * @return ElementGraph The graph a pattern is matched against
     */
    public static function graph(): ElementGraph
    {
        static $graph = null;

        return $graph ??= SampleGraph::elements();
    }
}
