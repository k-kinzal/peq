<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Usage\CatchEdge;
use App\Analyzer\Graph\Edge\Usage\ConstFetchEdge;
use App\Analyzer\Graph\Edge\Usage\FunctionCallEdge;
use App\Analyzer\Graph\Edge\Usage\InstanceofEdge;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\Usage\StaticCallEdge;
use App\Analyzer\Graph\Edge\Usage\StaticPropertyAccessEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use PhpParser\Node as PhpParserNode;

/**
 * Records what one written expression reaches out to.
 *
 * These are the relations a codebase writes on purpose — instantiating a class,
 * calling a method, reading a constant — and each of them names its target directly
 * enough to be read without inferring the type of anything. A call on a variable of
 * an unknown type names nothing that can be recorded, so it is not.
 *
 * A relation is only recorded when the expression stands inside a function or a
 * method: those are the symbols an impact analysis can report as affected. An
 * expression written in a constant or a property default has no such symbol to
 * belong to, and the relation it would describe has nowhere to hang.
 *
 * @visibility App\Analyzer\NativeAnalyzer
 */
final class UsageEmitter
{
    /**
     * Records the relations one expression describes.
     *
     * @param PhpParserNode $node  The expression met while walking
     * @param AnalysisScope $scope Where in the sources it is written
     *
     * @return list<Edge> The relations it describes
     */
    public static function emit(PhpParserNode $node, AnalysisScope $scope): array
    {
        $source = $scope->sourceNode();
        if (!$source instanceof MethodNode && !$source instanceof FunctionNode) {
            return [];
        }
        $meta = new FileMeta($scope->file, $node->getStartLine(), 1);

        return match (true) {
            $node instanceof PhpParserNode\Expr\ClassConstFetch => self::constantFetch($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Expr\New_ => self::instantiation($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Expr\StaticCall => self::staticCall($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Stmt\Catch_ => self::caught($node, $source, $meta),
            $node instanceof PhpParserNode\Expr\Instanceof_ => self::instanceOf($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Expr\FuncCall => self::functionCall($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Expr\NullsafeMethodCall,
            $node instanceof PhpParserNode\Expr\MethodCall => self::methodCall($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Expr\NullsafePropertyFetch,
            $node instanceof PhpParserNode\Expr\PropertyFetch => self::propertyAccess($node, $scope, $source, $meta),
            $node instanceof PhpParserNode\Expr\StaticPropertyFetch => self::staticPropertyAccess($node, $scope, $source, $meta),
            default => [],
        };
    }

    /**
     * Reports whether an expression is one a relation is recorded for.
     *
     * Walking a body asks this before asking for the relations, so that a body of a
     * thousand expressions costs one test each rather than one emission each. The two
     * are read together: a kind listed here but not in emit() costs one wasted call,
     * and one listed there but not here is never reached.
     *
     * @param PhpParserNode $node A node met while walking
     *
     * @return bool True when emit() has an answer for it
     */
    public static function records(PhpParserNode $node): bool
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
     * Records a constant being read off a named class.
     *
     * @param PhpParserNode\Expr\ClassConstFetch $node   The expression met while walking
     * @param AnalysisScope                      $scope  Where it is written
     * @param FunctionNode|MethodNode            $source The symbol it is written inside
     * @param FileMeta                           $meta   Where in the source it stands
     *
     * @return list<ConstFetchEdge> The relation it describes, or none
     */
    public static function constantFetch(PhpParserNode\Expr\ClassConstFetch $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if (!$node->class instanceof PhpParserNode\Name || !$node->name instanceof PhpParserNode\Identifier) {
            return [];
        }
        $className = $scope->resolveName($node->class);
        if ((new QualifiedName($className))->isBuiltinType()) {
            return [];
        }

        return [new ConstFetchEdge($source, new ConstantNode(ConstantNodeId::of($className, $node->name->toString()), false, null), $meta)];
    }

    /**
     * Records a named class being instantiated.
     *
     * @param PhpParserNode\Expr\New_ $node   The expression met while walking
     * @param AnalysisScope           $scope  Where it is written
     * @param FunctionNode|MethodNode $source The symbol it is written inside
     * @param FileMeta                $meta   Where in the source it stands
     *
     * @return list<InstantiationEdge> The relation it describes, or none
     */
    public static function instantiation(PhpParserNode\Expr\New_ $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if (!$node->class instanceof PhpParserNode\Name) {
            return [];
        }
        $className = $scope->resolveName($node->class);
        if ((new QualifiedName($className))->isBuiltinType()) {
            return [];
        }

        return [new InstantiationEdge($source, new ClassNode(ClassNodeId::of($className), false, null), $meta)];
    }

    /**
     * Records a static method being called on a named class.
     *
     * @param PhpParserNode\Expr\StaticCall $node   The expression met while walking
     * @param AnalysisScope                 $scope  Where it is written
     * @param FunctionNode|MethodNode       $source The symbol it is written inside
     * @param FileMeta                      $meta   Where in the source it stands
     *
     * @return list<StaticCallEdge> The relation it describes, or none
     */
    public static function staticCall(PhpParserNode\Expr\StaticCall $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if (!$node->class instanceof PhpParserNode\Name || !$node->name instanceof PhpParserNode\Identifier) {
            return [];
        }
        $className = $scope->resolveName($node->class);
        if ((new QualifiedName($className))->isBuiltinType()) {
            return [];
        }

        return [new StaticCallEdge($source, new MethodNode(MethodNodeId::of($className, $node->name->toString()), false, null), $meta)];
    }

    /**
     * Records the exception types a catch clause names.
     *
     * @param PhpParserNode\Stmt\Catch_ $node   The clause met while walking
     * @param FunctionNode|MethodNode   $source The symbol it is written inside
     * @param FileMeta                  $meta   Where in the source it stands
     *
     * @return list<CatchEdge> One relation per named type
     */
    public static function caught(PhpParserNode\Stmt\Catch_ $node, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        $items = [];
        foreach ($node->types as $type) {
            $items[] = new CatchEdge($source, new ClassNode(ClassNodeId::of($type->toString()), false, null), $meta);
        }

        return $items;
    }

    /**
     * Records a type named in an instanceof test.
     *
     * @param PhpParserNode\Expr\Instanceof_ $node   The expression met while walking
     * @param AnalysisScope                  $scope  Where it is written
     * @param FunctionNode|MethodNode        $source The symbol it is written inside
     * @param FileMeta                       $meta   Where in the source it stands
     *
     * @return list<InstanceofEdge> The relation it describes, or none
     */
    public static function instanceOf(PhpParserNode\Expr\Instanceof_ $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if (!$node->class instanceof PhpParserNode\Name) {
            return [];
        }
        $className = $scope->resolveName($node->class);
        if ((new QualifiedName($className))->isBuiltinType()) {
            return [];
        }

        return [new InstanceofEdge($source, new ClassNode(ClassNodeId::of($className), false, null), $meta)];
    }

    /**
     * Records a function being called by name.
     *
     * @param PhpParserNode\Expr\FuncCall $node   The expression met while walking
     * @param AnalysisScope               $scope  Where it is written
     * @param FunctionNode|MethodNode     $source The symbol it is written inside
     * @param FileMeta                    $meta   Where in the source it stands
     *
     * @return list<FunctionCallEdge> The relation it describes, or none
     */
    public static function functionCall(PhpParserNode\Expr\FuncCall $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if (!$node->name instanceof PhpParserNode\Name) {
            return [];
        }
        $functionName = $scope->resolveFunctionName($node->name);
        if ((new QualifiedName($functionName))->isBuiltinType()) {
            return [];
        }

        return [new FunctionCallEdge($source, new FunctionNode(FunctionNodeId::of($functionName), false, null), $meta)];
    }

    /**
     * Records a method being called on the object the code is written in.
     *
     * @param PhpParserNode\Expr\MethodCall|PhpParserNode\Expr\NullsafeMethodCall $node   The expression met while walking
     * @param AnalysisScope                                                       $scope  Where it is written
     * @param FunctionNode|MethodNode                                             $source The symbol it is written inside
     * @param FileMeta                                                            $meta   Where in the source it stands
     *
     * @return list<MethodCallEdge> The relation it describes, or none
     */
    public static function methodCall(PhpParserNode\Expr\MethodCall|PhpParserNode\Expr\NullsafeMethodCall $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if ($scope->className === null || !self::isThis($node->var) || !$node->name instanceof PhpParserNode\Identifier) {
            return [];
        }

        return [new MethodCallEdge($source, new MethodNode(MethodNodeId::of($scope->className, $node->name->toString()), false, null), $meta)];
    }

    /**
     * Records a property being read off the object the code is written in.
     *
     * @param PhpParserNode\Expr\NullsafePropertyFetch|PhpParserNode\Expr\PropertyFetch $node   The expression met while walking
     * @param AnalysisScope                                                             $scope  Where it is written
     * @param FunctionNode|MethodNode                                                   $source The symbol it is written inside
     * @param FileMeta                                                                  $meta   Where in the source it stands
     *
     * @return list<PropertyAccessEdge> The relation it describes, or none
     */
    public static function propertyAccess(PhpParserNode\Expr\NullsafePropertyFetch|PhpParserNode\Expr\PropertyFetch $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if ($scope->className === null || !self::isThis($node->var) || !$node->name instanceof PhpParserNode\Identifier) {
            return [];
        }

        return [new PropertyAccessEdge($source, new PropertyNode(PropertyNodeId::of($scope->className, $node->name->toString()), false, null), $meta)];
    }

    /**
     * Records a static property being read off a named class.
     *
     * @param PhpParserNode\Expr\StaticPropertyFetch $node   The expression met while walking
     * @param AnalysisScope                          $scope  Where it is written
     * @param FunctionNode|MethodNode                $source The symbol it is written inside
     * @param FileMeta                               $meta   Where in the source it stands
     *
     * @return list<StaticPropertyAccessEdge> The relation it describes, or none
     */
    public static function staticPropertyAccess(PhpParserNode\Expr\StaticPropertyFetch $node, AnalysisScope $scope, FunctionNode|MethodNode $source, FileMeta $meta): array
    {
        if (!$node->class instanceof PhpParserNode\Name || !$node->name instanceof PhpParserNode\VarLikeIdentifier) {
            return [];
        }
        $className = $scope->resolveName($node->class);
        if ((new QualifiedName($className))->isBuiltinType()) {
            return [];
        }

        return [new StaticPropertyAccessEdge($source, new PropertyNode(PropertyNodeId::of($className, $node->name->toString()), false, null), $meta)];
    }

    /**
     * Reports whether an expression is the object the code is written in.
     *
     * @param PhpParserNode\Expr $expression The receiver of a call or an access
     *
     * @return bool True when the receiver is written as `$this`
     */
    public static function isThis(PhpParserNode\Expr $expression): bool
    {
        return $expression instanceof PhpParserNode\Expr\Variable
            && is_string($expression->name)
            && $expression->name === 'this';
    }
}
