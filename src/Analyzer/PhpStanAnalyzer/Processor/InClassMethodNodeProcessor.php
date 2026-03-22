<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Node;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;

final class InClassMethodNodeProcessor
{
    /**
     * @return array<Edge|Node>
     */
    public static function process(InClassMethodNode $node, Scope $scope): array
    {
        $items = [];
        // Always re-parse the file to get original stmts.
        // PHPStan v2's CleaningVisitor strips expressions from method/closure bodies,
        // so the AST from getOriginalNode() may be incomplete.
        $stmts = self::getOriginalStmts($node, $scope);

        if ($stmts !== null) {
            $nodeFinder = new NodeFinder();
            $dependencies = $nodeFinder->find($stmts, function (PhpParserNode $n) {
                return $n instanceof PhpParserNode\Expr\ClassConstFetch
                    || $n instanceof PhpParserNode\Expr\New_
                    || $n instanceof PhpParserNode\Expr\StaticCall
                    || $n instanceof PhpParserNode\Stmt\Catch_
                    || $n instanceof PhpParserNode\Expr\Instanceof_
                    || $n instanceof PhpParserNode\Expr\FuncCall
                    || $n instanceof PhpParserNode\Expr\MethodCall
                    || $n instanceof PhpParserNode\Expr\NullsafeMethodCall
                    || $n instanceof PhpParserNode\Expr\PropertyFetch
                    || $n instanceof PhpParserNode\Expr\NullsafePropertyFetch
                    || $n instanceof PhpParserNode\Expr\StaticPropertyFetch;
            });

            foreach ($dependencies as $dep) {
                if ($dep instanceof PhpParserNode\Expr\ClassConstFetch) {
                    $items = array_merge($items, ConstFetchProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\New_) {
                    $items = array_merge($items, InstantiationProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\StaticCall) {
                    $items = array_merge($items, StaticCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Stmt\Catch_) {
                    $items = array_merge($items, CatchProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\Instanceof_) {
                    $items = array_merge($items, InstanceofProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\FuncCall) {
                    $items = array_merge($items, FunctionCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\NullsafeMethodCall) {
                    $items = array_merge($items, MethodCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\MethodCall) {
                    $items = array_merge($items, MethodCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\NullsafePropertyFetch) {
                    $items = array_merge($items, PropertyAccessProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\PropertyFetch) {
                    $items = array_merge($items, PropertyAccessProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\StaticPropertyFetch) {
                    $items = array_merge($items, StaticPropertyAccessProcessor::process($dep, $scope));
                }
            }
        }

        return $items;
    }

    /**
     * Always re-parses the source file to recover the full method body AST.
     *
     * DO NOT restore the stmts shortcut (checking $methodNode->stmts first).
     * PHPStan v2's CleaningVisitor can PARTIALLY strip expressions, leaving
     * stmts non-null but missing MethodCall, PropertyFetch, FuncCall nodes.
     * Always re-parsing guarantees the full original AST is available.
     *
     * @return null|PhpParserNode\Stmt[]
     */
    private static function getOriginalStmts(InClassMethodNode $node, Scope $scope): ?array
    {
        $methodNode = $node->getOriginalNode();

        $fileContent = file_get_contents($scope->getFile());
        if ($fileContent === false) {
            return null;
        }
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForHostVersion();

        try {
            $ast = $parser->parse($fileContent);
            if ($ast !== null) {
                $traverser = new NodeTraverser();
                $traverser->addVisitor(new NameResolver());
                $ast = $traverser->traverse($ast);

                $nodeFinder = new NodeFinder();
                $classReflection = $scope->getClassReflection();
                if ($classReflection !== null) {
                    $className = $classReflection->getName();
                    $classNode = $nodeFinder->findFirst($ast, function (PhpParserNode $n) use ($className) {
                        if (($n instanceof Class_ || $n instanceof Interface_ || $n instanceof Trait_ || $n instanceof Enum_)
                            && isset($n->namespacedName)) {
                            return $n->namespacedName->toString() === $className;
                        }

                        return false;
                    });

                    if ($classNode instanceof Class_ || $classNode instanceof Trait_ || $classNode instanceof Enum_) {
                        $methodName = $methodNode->name->toString();
                        $foundMethod = $nodeFinder->findFirst($classNode->stmts, function (PhpParserNode $n) use ($methodName) {
                            return $n instanceof ClassMethod && $n->name->toString() === $methodName;
                        });

                        if ($foundMethod instanceof ClassMethod) {
                            return $foundMethod->stmts;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore parsing errors
        }

        return null;
    }
}
