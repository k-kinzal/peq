<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration;

use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;

/**
 * Reads what a callable declaration promises about being called.
 *
 * The graph records the class-likes a signature names because a change to one of them
 * reaches the callable. The signature itself is a different fact: it is what callers
 * were written against, so it is what a change to the callable reaches. Reading it
 * once, where the declaration is met, is what makes "which handlers take a request"
 * answerable without going back to the file.
 *
 * @visibility App\Analyzer
 */
final class WrittenSignature
{
    /**
     * Reads the signature of a callable declaration.
     *
     * @param FunctionLike $node The declaration met while walking
     *
     * @example A signature is read as the declaration writes it
     *     $written = new \PhpParser\Node\Stmt\ClassMethod('total', [
     *         'params' => [new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('rate'), type: new \PhpParser\Node\Identifier('int'))],
     *         'returnType' => new \PhpParser\Node\Identifier('int'),
     *     ]);
     *     \App\Analyzer\Declaration\WrittenSignature::of($written)->toString() // => '(int $rate): int'
     *
     * @return Signature What the declaration promises
     */
    public static function of(FunctionLike $node): Signature
    {
        $parameters = [];
        foreach ($node->getParams() as $param) {
            $parameter = self::parameter($param);
            if ($parameter !== null) {
                $parameters[] = $parameter;
            }
        }

        return new Signature($parameters, TypeText::of($node->getReturnType()));
    }

    /**
     * Reads one parameter of a declaration.
     *
     * A parameter destructured into something other than a variable cannot be named,
     * and a parameter that cannot be named cannot be part of the promise a caller was
     * written against, so it is left out rather than invented.
     *
     * @param Param $param The parameter as it was written
     *
     * @example A promoted parameter says that it also declares a property
     *     $written = new \PhpParser\Node\Param(
     *         new \PhpParser\Node\Expr\Variable('amount'),
     *         type: new \PhpParser\Node\Identifier('int'),
     *         flags: \PhpParser\Modifiers::PRIVATE,
     *     );
     *     \App\Analyzer\Declaration\WrittenSignature::parameter($written)?->promoted // => true
     *
     * @return null|Parameter The parameter, or null when it has no name to record
     */
    public static function parameter(Param $param): ?Parameter
    {
        if (!$param->var instanceof Variable || !is_string($param->var->name)) {
            return null;
        }

        return new Parameter(
            name: $param->var->name,
            type: TypeText::of($param->type),
            optional: $param->default !== null,
            variadic: $param->variadic,
            byRef: $param->byRef,
            promoted: $param->isPromoted(),
        );
    }
}
