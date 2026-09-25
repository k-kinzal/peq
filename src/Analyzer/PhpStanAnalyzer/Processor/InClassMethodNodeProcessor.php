<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\CallBody;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\CatchProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\ConstFetchProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\FunctionCallProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\InstanceofProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\InstantiationProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\MethodCallProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\PropertyAccessProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticCallProcessor;
use App\Analyzer\PhpStanAnalyzer\Processor\Usage\StaticPropertyAccessProcessor;
use App\Analyzer\PhpStanAnalyzer\ReparsedSource;
use App\Analyzer\PhpStanAnalyzer\SourceResolver;
use PhpParser\Node as PhpParserNode;
use PhpParser\NodeTraverser;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;

/**
 * Records everything the body of a method reaches out to.
 *
 * The body is read from the file rather than from the tree PHPStan supplies, because
 * that tree has had expressions cleaned out of it. Each expression found is handed to
 * the processor that knows how to turn it into nodes and edges.
 *
 * @visibility parent
 */
final class InClassMethodNodeProcessor
{
    /**
     * Records the relations written inside one method body.
     *
     * @param InClassMethodNode $node   The method the analyser is standing in
     * @param Scope             $scope  The analyser scope of that method
     * @param ReparsedSource    $source The file contents read as written
     *
     * @return list<Edge|Node> The nodes and relations the body describes
     */
    public static function process(InClassMethodNode $node, Scope $scope, ReparsedSource $source): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $body = $source->methodBody(
            $scope->getFile(),
            $classReflection->getName(),
            $node->getOriginalNode()->name->toString(),
        );
        if ($body === null) {
            return [];
        }

        $sourceNode = SourceResolver::resolve($scope);
        $items = [];
        $visitor = new CallBody(self::handles(...));
        (new NodeTraverser($visitor))->traverse($body);
        foreach ($visitor->expressions as $usage) {
            array_push($items, ...self::dispatch($usage, $scope, $sourceNode));
        }

        return $items;
    }

    /**
     * Reports whether an expression is one this processor records a relation for.
     *
     * This mirrors the arms of dispatch(): searching for exactly the expressions that
     * have a processor is what keeps the whole tree from being materialised. The two
     * are read together — a kind listed here but not there costs one wasted call, and
     * one listed there but not here is simply never reached.
     *
     * @param PhpParserNode $node A node met while walking the method body
     *
     * @return bool True when dispatch() has an arm for it
     */
    public static function handles(PhpParserNode $node): bool
    {
        return $node instanceof PhpParserNode\Expr\ClassConstFetch
            || $node instanceof PhpParserNode\Expr\New_
            || $node instanceof PhpParserNode\Expr\StaticCall
            || $node instanceof PhpParserNode\Stmt\Catch_
            || $node instanceof PhpParserNode\Expr\Instanceof_
            || $node instanceof PhpParserNode\Expr\FuncCall
            || $node instanceof PhpParserNode\Expr\MethodCall
            || $node instanceof PhpParserNode\Expr\NullsafeMethodCall
            || $node instanceof PhpParserNode\Expr\PropertyFetch
            || $node instanceof PhpParserNode\Expr\NullsafePropertyFetch
            || $node instanceof PhpParserNode\Expr\StaticPropertyFetch;
    }

    /**
     * Hands one expression to the processor that records its relation.
     *
     * @param PhpParserNode $node   The expression met in the method body
     * @param Scope         $scope  The analyser scope of the method
     * @param Node          $source The method the expression is written inside
     *
     * @return list<Edge|Node> What that processor reported, or nothing for an expression with no arm
     */
    public static function dispatch(PhpParserNode $node, Scope $scope, Node $source): array
    {
        return match (true) {
            $node instanceof PhpParserNode\Expr\ClassConstFetch => ConstFetchProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\New_ => InstantiationProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\StaticCall => StaticCallProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Stmt\Catch_ => CatchProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\Instanceof_ => InstanceofProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\FuncCall => FunctionCallProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\NullsafeMethodCall => MethodCallProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\MethodCall => MethodCallProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\NullsafePropertyFetch => PropertyAccessProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\PropertyFetch => PropertyAccessProcessor::process($node, $scope, $source),
            $node instanceof PhpParserNode\Expr\StaticPropertyFetch => StaticPropertyAccessProcessor::process($node, $scope, $source),
            default => [],
        };
    }
}
