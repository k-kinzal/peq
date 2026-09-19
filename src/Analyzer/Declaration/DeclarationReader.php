<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;

/**
 * Reads what a declaration says about itself, whichever way the sources were read.
 *
 * peq can read a codebase through PHPStan or directly, and both arrive at the same
 * syntax nodes; the difference between them is how a written name is resolved, not
 * what a declaration says. Everything that does not need a resolver therefore lives
 * here once, and the two analyzers differ only where they genuinely differ.
 *
 * Attributes are the exception, and they are handed in already resolved: an attribute
 * is recognised by the class it names, and only the analyser knows which class a
 * written name meant.
 *
 * @visibility App\Analyzer
 */
final class DeclarationReader
{
    /**
     * Reads what a class-like declaration says about itself.
     *
     * An interface and a trait carry no keyword PHP writes, so they report none; what
     * makes them what they are is their kind, which the node already carries.
     *
     * @param ClassLike            $node       The declaration met while walking
     * @param list<AttributeUsage> $attributes The attributes written on it, already resolved
     *
     * @example A final class says so
     *     $written = new \PhpParser\Node\Stmt\Class_('Invoice', ['flags' => \PhpParser\Modifiers::FINAL]);
     *     \App\Analyzer\Declaration\DeclarationReader::forClassLike($written, [])->modifiers->final // => true
     *
     * @return SymbolDeclaration What the declaration says
     */
    public static function forClassLike(ClassLike $node, array $attributes): SymbolDeclaration
    {
        $class = $node instanceof Class_ ? $node : null;

        return new SymbolDeclaration(
            modifiers: new Modifiers(
                abstract: $class !== null && $class->isAbstract(),
                final: $class !== null && $class->isFinal(),
                readonly: $class !== null && $class->isReadonly(),
            ),
            attributes: $attributes,
            deprecated: self::deprecated($node),
        );
    }

    /**
     * Reads what a method or function declaration says about itself.
     *
     * A function carries neither a visibility nor a keyword, so only a method reports
     * them; both report the signature, which is the part callers are written against.
     *
     * @param FunctionLike         $node       The declaration met while walking
     * @param list<AttributeUsage> $attributes The attributes written on it, already resolved
     *
     * @example A private method says so
     *     $written = new \PhpParser\Node\Stmt\ClassMethod('total', ['flags' => \PhpParser\Modifiers::PRIVATE]);
     *     \App\Analyzer\Declaration\DeclarationReader::forCallable($written, [])->visibility // => \App\Analyzer\Graph\Declaration\Visibility::Private
     * @example A function has no visibility to report
     *     $written = new \PhpParser\Node\Stmt\Function_('total');
     *     \App\Analyzer\Declaration\DeclarationReader::forCallable($written, [])->visibility // => null
     *
     * @return SymbolDeclaration What the declaration says
     */
    public static function forCallable(FunctionLike $node, array $attributes): SymbolDeclaration
    {
        $method = $node instanceof ClassMethod ? $node : null;

        return new SymbolDeclaration(
            visibility: $method === null ? null : self::visibility($method->isPrivate(), $method->isProtected()),
            modifiers: new Modifiers(
                static: $method !== null && $method->isStatic(),
                abstract: $method !== null && $method->isAbstract(),
                final: $method !== null && $method->isFinal(),
            ),
            signature: WrittenSignature::of($node),
            attributes: $attributes,
            deprecated: self::deprecated($node),
        );
    }

    /**
     * Reads what a property statement says about the properties it declares.
     *
     * One statement declares every property written after its keywords, and the
     * keywords and the type belong to all of them, so all of them are described by
     * the same reading.
     *
     * @param Property             $node       The statement met while walking
     * @param list<AttributeUsage> $attributes The attributes written on it, already resolved
     *
     * @example A readonly property says so
     *     $written = new \PhpParser\Node\Stmt\Property(\PhpParser\Modifiers::PRIVATE | \PhpParser\Modifiers::READONLY, []);
     *     \App\Analyzer\Declaration\DeclarationReader::forProperty($written, [])->modifiers->readonly // => true
     *
     * @return SymbolDeclaration What the statement says
     */
    public static function forProperty(Property $node, array $attributes): SymbolDeclaration
    {
        return new SymbolDeclaration(
            visibility: self::visibility($node->isPrivate(), $node->isProtected()),
            modifiers: new Modifiers(
                static: $node->isStatic(),
                abstract: $node->isAbstract(),
                final: $node->isFinal(),
                readonly: $node->isReadonly(),
            ),
            attributes: $attributes,
            type: TypeText::of($node->type),
            deprecated: self::deprecated($node),
        );
    }

    /**
     * Reads what a promoted constructor parameter says about the property it declares.
     *
     * The parameter and the property it promotes into are written as one thing, so
     * the keywords read here are the property's: what the constructor promises about
     * being called is read as part of its signature instead.
     *
     * @param Param                $node       The parameter met while walking
     * @param list<AttributeUsage> $attributes The attributes written on it, already resolved
     *
     * @example A promoted parameter declares a property of its own visibility
     *     $written = new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable('amount'), flags: \PhpParser\Modifiers::PROTECTED);
     *     \App\Analyzer\Declaration\DeclarationReader::forPromotedProperty($written, [])->visibility // => \App\Analyzer\Graph\Declaration\Visibility::Protected
     *
     * @return SymbolDeclaration What the parameter says about the property
     */
    public static function forPromotedProperty(Param $node, array $attributes): SymbolDeclaration
    {
        return new SymbolDeclaration(
            visibility: self::visibility($node->isPrivate(), $node->isProtected()),
            modifiers: new Modifiers(readonly: $node->isReadonly()),
            attributes: $attributes,
            type: TypeText::of($node->type),
            value: ExpressionText::of($node->default),
            deprecated: self::deprecated($node),
        );
    }

    /**
     * Reads what a constant statement says about one of the constants it declares.
     *
     * The value belongs to the single constant rather than to the statement, because
     * `const A = 1, B = 2;` declares two constants with two different values.
     *
     * @param ClassConst           $node       The statement met while walking
     * @param Const_               $constant   The one constant being described
     * @param list<AttributeUsage> $attributes The attributes written on it, already resolved
     *
     * @example A constant keeps the value it was given
     *     $written = new \PhpParser\Node\Const_('RATE', new \PhpParser\Node\Scalar\Int_(3));
     *     $statement = new \PhpParser\Node\Stmt\ClassConst([$written]);
     *     \App\Analyzer\Declaration\DeclarationReader::forConstant($statement, $written, [])->value // => '3'
     *
     * @return SymbolDeclaration What the statement says about that constant
     */
    public static function forConstant(ClassConst $node, Const_ $constant, array $attributes): SymbolDeclaration
    {
        return new SymbolDeclaration(
            visibility: self::visibility($node->isPrivate(), $node->isProtected()),
            modifiers: new Modifiers(final: $node->isFinal()),
            attributes: $attributes,
            type: TypeText::of($node->type),
            value: ExpressionText::of($constant->value),
            deprecated: self::deprecated($node),
        );
    }

    /**
     * Reads what an enum case says about itself.
     *
     * A case of a backed enum carries the value that backs it, and that value is the
     * one a serialised form of the enum is written as, so it is worth recording. A
     * pure enum's case carries none.
     *
     * @param EnumCase             $node       The case met while walking
     * @param list<AttributeUsage> $attributes The attributes written on it, already resolved
     *
     * @example A backed case keeps the value that backs it
     *     $written = new \PhpParser\Node\Stmt\EnumCase('Draft', new \PhpParser\Node\Scalar\String_('draft'));
     *     \App\Analyzer\Declaration\DeclarationReader::forEnumCase($written, [])->value // => "'draft'"
     *
     * @return SymbolDeclaration What the case says
     */
    public static function forEnumCase(EnumCase $node, array $attributes): SymbolDeclaration
    {
        return new SymbolDeclaration(
            attributes: $attributes,
            value: ExpressionText::of($node->expr),
            deprecated: self::deprecated($node),
        );
    }

    /**
     * Names the visibility a declaration carries.
     *
     * PHP writes no keyword for the widest one, so a declaration that says nothing is
     * public. Asking the two narrower questions rather than reading a flag keeps this
     * usable from every kind of declaration, each of which spells its flags its own way.
     *
     * @param bool $private   Whether the declaration is written private
     * @param bool $protected Whether the declaration is written protected
     *
     * @example A declaration that says nothing is reachable from everywhere
     *     \App\Analyzer\Declaration\DeclarationReader::visibility(false, false) // => \App\Analyzer\Graph\Declaration\Visibility::Public
     *
     * @return Visibility The visibility it carries
     */
    public static function visibility(bool $private, bool $protected): Visibility
    {
        if ($private) {
            return Visibility::Private;
        }

        return $protected ? Visibility::Protected : Visibility::Public;
    }

    /**
     * Reports whether a declaration marks itself deprecated.
     *
     * PHP has no keyword for it, so the answer is in the doc block, where the tag has
     * to start a line the way every other doc block tag does. That is deliberately
     * the only place it is looked for: prose that mentions the word is prose.
     *
     * @param Node $node The declaration met while walking
     *
     * @example A declaration with no doc block says nothing about being deprecated
     *     \App\Analyzer\Declaration\DeclarationReader::deprecated(new \PhpParser\Node\Stmt\Class_('Invoice')) // => false
     *
     * @return bool True when the doc block carries a deprecation tag
     */
    public static function deprecated(Node $node): bool
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return false;
        }

        return preg_match('/^\s*(?:\/\*\*)?\s*\*?\s*@deprecated\b/m', $doc->getText()) === 1;
    }
}
