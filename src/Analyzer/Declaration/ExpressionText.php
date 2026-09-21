<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\PrettyPrinter\Standard;

/**
 * Writes an expression a declaration contains back out as source text.
 *
 * Analysis does not run the code it reads, so the value an enum case is backed by or
 * the route an attribute is given is not a value here — it is a piece of syntax. The
 * honest thing to record is therefore the syntax, and the honest way to record it is
 * to print it back.
 *
 * That keeps `#[Route('/users')]` recognisable as the thing a reader wrote, which is
 * what a query against it has to match, and it keeps an expression that no analysis
 * could evaluate — a constant from another file, a concatenation — from being dropped
 * for being inconvenient.
 *
 * @visibility App\Analyzer
 */
final class ExpressionText
{
    /**
     * Writes an expression out as the text that wrote it.
     *
     * @param null|Expr $expression The expression, or null where the declaration wrote none
     *
     * @example A literal reads as it was written
     *     \App\Analyzer\Declaration\ExpressionText::of(new \PhpParser\Node\Scalar\String_('/users')) // => "'/users'"
     * @example A declaration that wrote no expression says nothing
     *     \App\Analyzer\Declaration\ExpressionText::of(null) // => null
     *
     * @return null|string The expression as written, or null when there was none
     */
    public static function of(?Expr $expression): ?string
    {
        if ($expression === null) {
            return null;
        }

        return (new Standard())->prettyPrintExpr($expression);
    }

    /**
     * Writes an argument out as the text that wrote it, name and spread included.
     *
     * A named argument is part of how the argument was given, and an attribute is
     * routinely written with named arguments, so dropping the name would make two
     * different attributes read the same.
     *
     * @param Arg|VariadicPlaceholder $argument The argument as it was written
     *
     * @example A named argument keeps the name that placed it
     *     $given = new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_('/users'), name: new \PhpParser\Node\Identifier('path'));
     *     \App\Analyzer\Declaration\ExpressionText::argument($given) // => "path: '/users'"
     * @example A first-class callable placeholder is written the way PHP writes it
     *     \App\Analyzer\Declaration\ExpressionText::argument(new \PhpParser\Node\VariadicPlaceholder()) // => '...'
     *
     * @return string The argument as written
     */
    public static function argument(Arg|VariadicPlaceholder $argument): string
    {
        if ($argument instanceof VariadicPlaceholder) {
            return '...';
        }

        return ($argument->name === null ? '' : $argument->name->toString().': ')
            .($argument->unpack ? '...' : '')
            .(self::of($argument->value) ?? '');
    }
}
