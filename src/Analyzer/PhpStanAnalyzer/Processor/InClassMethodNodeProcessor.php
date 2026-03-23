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
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;

final class InClassMethodNodeProcessor
{
    /** @var array<string, false|PhpParserNode\Stmt[]> */
    private static array $astCache = [];

    private static ?Parser $parser = null;

    private static ?NodeFinder $nodeFinder = null;

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
            $finder = self::$nodeFinder ??= new NodeFinder();
            $dependencies = $finder->find($stmts, function (PhpParserNode $n) {
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
                    array_push($items, ...ConstFetchProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\New_) {
                    array_push($items, ...InstantiationProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\StaticCall) {
                    array_push($items, ...StaticCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Stmt\Catch_) {
                    array_push($items, ...CatchProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\Instanceof_) {
                    array_push($items, ...InstanceofProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\FuncCall) {
                    array_push($items, ...FunctionCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\NullsafeMethodCall) {
                    array_push($items, ...MethodCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\MethodCall) {
                    array_push($items, ...MethodCallProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\NullsafePropertyFetch) {
                    array_push($items, ...PropertyAccessProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\PropertyFetch) {
                    array_push($items, ...PropertyAccessProcessor::process($dep, $scope));
                } elseif ($dep instanceof PhpParserNode\Expr\StaticPropertyFetch) {
                    array_push($items, ...StaticPropertyAccessProcessor::process($dep, $scope));
                }
            }
        }

        return $items;
    }

    /**
     * Returns the parsed and name-resolved AST for the given file, using a cache
     * to avoid re-parsing the same file for every method it contains.
     *
     * @return null|PhpParserNode\Stmt[]
     */
    private static function getParsedAst(string $filePath): ?array
    {
        if (array_key_exists($filePath, self::$astCache)) {
            $cached = self::$astCache[$filePath];

            return $cached === false ? null : $cached;
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            self::$astCache[$filePath] = false;

            return null;
        }

        $parser = self::$parser ??= (new ParserFactory())->createForHostVersion();

        try {
            $ast = $parser->parse($fileContent);
            if ($ast === null) {
                self::$astCache[$filePath] = false;

                return null;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());

            /** @var PhpParserNode\Stmt[] $ast */
            $ast = $traverser->traverse($ast);
            self::$astCache[$filePath] = $ast;

            return $ast;
        } catch (\Throwable) {
            self::$astCache[$filePath] = false;

            return null;
        }
    }

    /**
     * Recovers the full method body AST by looking up the cached parsed file.
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
        $ast = self::getParsedAst($scope->getFile());
        if ($ast === null) {
            return null;
        }

        $methodNode = $node->getOriginalNode();
        $nodeFinder = self::$nodeFinder ??= new NodeFinder();
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return null;
        }

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

        return null;
    }
}
