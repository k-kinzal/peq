<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Collector;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Processor\CatchProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\ClassConstProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\ClassLikeProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\ConstFetchProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\EnumCaseProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\FunctionLikeProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\InstanceofProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\InstantiationProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\PromotedPropertyProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\PropertyProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\StaticCallProcessor;
use PhpParser\Modifiers;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Collects dependency information from PHP nodes.
 *
 * @implements Collector<PhpParserNode, array<Node|Edge>>
 */
final class DependencyCollector implements Collector
{
    public function getNodeType(): string
    {
        return PhpParserNode::class;
    }

    /**
     * @return null|array<Edge|Node>
     */
    public function processNode(PhpParserNode $node, Scope $scope): ?array
    {
        $items = match (true) {
            $node instanceof ClassLike => ClassLikeProcessor::process($node, $scope),
            $node instanceof PhpParserNode\FunctionLike => FunctionLikeProcessor::process($node, $scope),
            $node instanceof Property => PropertyProcessor::process($node, $scope),
            $node instanceof ClassConst => ClassConstProcessor::process($node, $scope),
            $node instanceof EnumCase => EnumCaseProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\ClassConstFetch => ConstFetchProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\New_ => InstantiationProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\StaticCall => StaticCallProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Stmt\Catch_ => CatchProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\Instanceof_ => InstanceofProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Param && ($node->flags & Modifiers::VISIBILITY_MASK) !== 0 => PromotedPropertyProcessor::process($node, $scope),
            default => [],
        };

        if ($items === []) {
            return null;
        }

        return $items;
    }
}
