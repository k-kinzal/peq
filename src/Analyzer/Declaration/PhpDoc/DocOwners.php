<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node as Symbol;
use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Finds existing graph symbols without creating declarations from annotations.
 */
final class DocOwners
{
    /**
     * @return null|list<Symbol> null retains the enclosing declaration
     */
    public static function of(Node $node, DocScope $scope, Graph $graph): ?array
    {
        $names = match (true) {
            $node instanceof Stmt\ClassLike => [$scope->class ?? ''],
            $node instanceof Stmt\Function_ => [$node->namespacedName?->toString() ?? ''],
            $node instanceof Stmt\ClassMethod => [$scope->class.'::'.$node->name->toString()],
            $node instanceof Node\Param && $node->flags !== 0 && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) => [$scope->class.'::'.$node->var->name],
            $node instanceof Stmt\Property => array_map(static fn (Node\PropertyItem $property): string => $scope->class.'::'.$property->name->toString(), $node->props),
            $node instanceof Stmt\ClassConst => array_map(static fn (Node\Const_ $constant): string => $scope->class.'::'.$constant->name->toString(), $node->consts),
            $node instanceof Stmt\EnumCase => [$scope->class.'::'.$node->name->toString()],
            default => null,
        };
        if ($names === null) {
            return null;
        }
        $symbols = array_values(array_filter(array_map($graph->nodeNamed(...), $names)));
        if ($symbols === [] && $node instanceof Stmt\ClassMethod) {
            return null;
        }

        return $symbols;
    }
}
