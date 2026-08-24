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
use App\Analyzer\PhpStanAnalyzer\Processor\FunctionCallProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\FunctionLikeProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\InstanceofProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\InstantiationProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\MethodCallProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\PromotedPropertyProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\PropertyAccessProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\PropertyProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\StaticCallProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\StaticPropertyAccessProcessor;
use PhpParser\Modifiers;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Reports the relations written outside method bodies.
 *
 * Declarations — what a class declares, what a signature says, what a property is
 * typed as — are read straight from the tree the analyser supplies, because nothing
 * strips them. Usage expressions are different: inside a method body the analyser's
 * tree is incomplete, so InClassMethodCollector handles those by reading the file
 * again. This collector therefore stops at the door of a method body, which is what
 * keeps a call from being recorded twice.
 *
 * @implements Collector<PhpParserNode, list<Edge|Node>>
 *
 * @visibility parent
 */
final class DependencyCollector implements Collector
{
    /**
     * Names the analyser node this collector is called for.
     *
     * @return class-string<PhpParserNode> The class of the node kind this collector reads
     */
    public function getNodeType(): string
    {
        return PhpParserNode::class;
    }

    /**
     * Reports what one node of the syntax tree declares or uses.
     *
     * A node is read as a declaration first. Only if it is none is it read as a
     * usage, and then only outside a method body, because inside one the same
     * expression is read from the re-parsed source instead.
     *
     * @param PhpParserNode $node  The node the analyser reached
     * @param Scope         $scope The analyser scope it was reached in
     *
     * @return null|list<Edge|Node> The relations found, or null when there are none
     */
    public function processNode(PhpParserNode $node, Scope $scope): ?array
    {
        $items = self::declaration($node, $scope);
        if ($items === null) {
            $inClassMethod = $scope->isInClass() && $scope->getFunction() !== null;
            $items = $inClassMethod ? [] : (self::usage($node, $scope) ?? []);
        }

        return $items === [] ? null : $items;
    }

    /**
     * Reads a node as a declaration, if it is one.
     *
     * @param PhpParserNode $node  The node the analyser reached
     * @param Scope         $scope The analyser scope it was reached in
     *
     * @return null|list<Edge|Node> What the declaration describes, or null when the node declares nothing
     */
    public static function declaration(PhpParserNode $node, Scope $scope): ?array
    {
        return match (true) {
            $node instanceof ClassLike => ClassLikeProcessor::process($node, $scope),
            $node instanceof PhpParserNode\FunctionLike => FunctionLikeProcessor::process($node, $scope),
            $node instanceof Property => PropertyProcessor::process($node, $scope),
            $node instanceof ClassConst => ClassConstProcessor::process($node, $scope),
            $node instanceof EnumCase => EnumCaseProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Param && ($node->flags & Modifiers::VISIBILITY_MASK) !== 0 => PromotedPropertyProcessor::process($node, $scope),
            default => null,
        };
    }

    /**
     * Reads a node as a usage expression, if it is one.
     *
     * @param PhpParserNode $node  The node the analyser reached
     * @param Scope         $scope The analyser scope it was reached in
     *
     * @return null|list<Edge|Node> What the usage describes, or null when the node uses nothing
     */
    public static function usage(PhpParserNode $node, Scope $scope): ?array
    {
        return match (true) {
            $node instanceof PhpParserNode\Expr\ClassConstFetch => ConstFetchProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\New_ => InstantiationProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\StaticCall => StaticCallProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Stmt\Catch_ => CatchProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\Instanceof_ => InstanceofProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\FuncCall => FunctionCallProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\NullsafeMethodCall => MethodCallProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\MethodCall => MethodCallProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\NullsafePropertyFetch => PropertyAccessProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\PropertyFetch => PropertyAccessProcessor::process($node, $scope),
            $node instanceof PhpParserNode\Expr\StaticPropertyFetch => StaticPropertyAccessProcessor::process($node, $scope),
            default => null,
        };
    }
}
