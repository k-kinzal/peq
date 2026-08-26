<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use PhpParser\Node as PhpParserNode;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Collectors\Collector;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\MissingServiceException;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use RuntimeException;

/**
 * Runs one collector over one file and reports what it collected.
 *
 * A collector is only meaningful inside an analysis: it is handed syntax nodes
 * together with the scope PHPStan resolved for them. Driving that from a test means
 * assembling a scope resolver, a scope factory and a parser, which is wiring rather
 * than a test. It lives here so a collector test reads as one call and one
 * assertion.
 */
final class CollectorRun
{
    /**
     * Collects everything a collector reports for a file.
     *
     * @param Collector<PhpParserNode, mixed> $collector The collector to run
     * @param string                          $file      Absolute path of the file to analyse
     * @param Container                       $container The PHPStan container of the test case
     * @param Parser                          $parser    The PHPStan parser of the test case
     *
     * @return list<Edge|Node> Everything the collector reported, in the order it reported it
     *
     * @throws RuntimeException If the container or the file cannot support a run at all
     */
    public static function over(Collector $collector, string $file, Container $container, Parser $parser): array
    {
        $collected = [];
        $nodeType = $collector->getNodeType();

        $visit = static function (PhpParserNode $node, Scope $scope) use ($collector, $nodeType, &$collected): void {
            if (!$node instanceof $nodeType) {
                return;
            }

            $reported = $collector->processNode($node, $scope);
            if (!is_array($reported)) {
                return;
            }

            foreach ($reported as $item) {
                if ($item instanceof Node || $item instanceof Edge) {
                    $collected[] = $item;
                }
            }
        };

        try {
            $container->getByType(NodeScopeResolver::class)->processNodes(
                $parser->parseFile($file),
                $container->getByType(ScopeFactory::class)->create(ScopeContext::create($file)),
                $visit,
            );
        } catch (MissingServiceException|ParserErrorsException $failure) {
            throw new RuntimeException(
                sprintf('Cannot run a collector over "%s": %s', $file, $failure->getMessage()),
                0,
                $failure,
            );
        }

        return $collected;
    }

    /**
     * Reports the string form of every node identifier among collected items.
     *
     * @param list<Edge|Node> $collected What a collector reported
     *
     * @return list<string> The identifiers of the nodes it reported
     */
    public static function nodeNames(array $collected): array
    {
        $names = [];
        foreach ($collected as $item) {
            if ($item instanceof Node) {
                $names[] = $item->id()->toString();
            }
        }

        return $names;
    }

    /**
     * Describes every symbol among collected items, with what analysis established about it.
     *
     * A symbol the analysis actually read is resolved; one it only met as the target of
     * a relation is not. Rendering that alongside the name is what lets a test state the
     * difference rather than only the names.
     *
     * @param list<Edge|Node> $collected What a collector reported
     *
     * @return list<string> One description per symbol, in a stable order
     */
    public static function sortedSymbolDescriptions(array $collected): array
    {
        $described = [];
        foreach ($collected as $item) {
            if ($item instanceof Node) {
                $described[] = $item->id()->toString().' ('.($item->resolved() ? 'analysed' : 'referenced').')';
            }
        }
        sort($described);

        return array_values(array_unique($described));
    }

    /**
     * Describes every relation among collected items, sorted, for an exact comparison.
     *
     * @param list<Edge|Node> $collected What a collector reported
     *
     * @return list<string> One description per relation, in a stable order
     */
    public static function sortedEdgeDescriptions(array $collected): array
    {
        $descriptions = self::edgeDescriptions($collected);
        sort($descriptions);

        return $descriptions;
    }

    /**
     * Describes every relation among collected items as "from -[kind]-> to".
     *
     * @param list<Edge|Node> $collected What a collector reported
     *
     * @return list<string> One description per relation it reported
     */
    public static function edgeDescriptions(array $collected): array
    {
        $descriptions = [];
        foreach ($collected as $item) {
            if ($item instanceof Edge) {
                $descriptions[] = sprintf(
                    '%s -[%s]-> %s',
                    $item->from()->toString(),
                    $item->kind()->value,
                    $item->to()->toString(),
                );
            }
        }

        return $descriptions;
    }
}
