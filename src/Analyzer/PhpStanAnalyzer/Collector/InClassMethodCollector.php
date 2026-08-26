<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Processor\InClassMethodNodeProcessor;
use App\Analyzer\PhpStanAnalyzer\ReparsedSource;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassMethodNode;

/**
 * Reports the relations written inside method bodies.
 *
 * Method bodies need the file to be read again, and this collector owns the memory
 * of what has already been read: one instance lives for one analysis run, so a file
 * holding twenty methods is parsed once rather than twenty times, and nothing is
 * carried over between runs.
 *
 * @implements Collector<InClassMethodNode, list<Edge|Node>>
 *
 * @visibility parent
 */
final class InClassMethodCollector implements Collector
{
    /**
     * @param ReparsedSource $source The files read as written, remembered for this run
     */
    public function __construct(
        private readonly ReparsedSource $source = new ReparsedSource(),
    ) {}

    /**
     * Names the analyser node this collector is called for.
     *
     * @return class-string<InClassMethodNode> The class of the node kind this collector reads
     */
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * Reports what the method body reaches out to.
     *
     * The analyser only ever hands over the node type getNodeType() names, which is
     * what the parameter is documented as: checking it again here would be checking
     * PHPStan rather than the source being analysed.
     *
     * @param InClassMethodNode $node  The method the analyser reached
     * @param Scope             $scope The analyser scope it was reached in
     *
     * @return null|list<Edge|Node> The relations found, or null when there are none
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): ?array
    {
        $items = InClassMethodNodeProcessor::process($node, $scope, $this->source);

        return $items === [] ? null : $items;
    }
}
