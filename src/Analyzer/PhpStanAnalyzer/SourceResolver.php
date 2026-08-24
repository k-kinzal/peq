<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use PHPStan\Analyser\Scope;

/**
 * Names the symbol a relation is written inside.
 *
 * Every edge starts somewhere, and that somewhere is whatever declaration the
 * analyser's cursor is standing in. This turns the analyser's notion of a scope
 * into the graph's notion of a node.
 *
 * @visibility namespace
 */
final class SourceResolver
{
    /**
     * Resolves the node that owns the code currently being analysed.
     *
     * Inside a method, the owner is that method. Inside a class but outside any of
     * its methods — a property default or a constant expression — the owner is the
     * class itself, and relations written there are attributed to it. Outside any
     * class the owner is the enclosing function, and at the top level of a file there
     * is no declaration to attribute anything to, so the file stands in as an
     * unresolved owner.
     *
     * @param Scope $scope The analyser scope the relation was met in
     *
     * @return Node The node the relation is written inside
     */
    public static function resolve(Scope $scope): Node
    {
        if ($scope->isInClass()) {
            $className = $scope->getClassReflection()->getName();
            $function = $scope->getFunction();

            if ($function !== null) {
                return new MethodNode(MethodNodeId::of($className, $function->getName()), true, null);
            }

            return new ClassNode(ClassNodeId::of($className), true, null);
        }

        $function = $scope->getFunction();
        if ($function !== null) {
            return new FunctionNode(FunctionNodeId::of($function->getName()), true, null);
        }

        return new UnknownNode(new UnknownNodeId($scope->getFile()));
    }
}
