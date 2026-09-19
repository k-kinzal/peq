<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * Writes a declared type back out the way the source writes it.
 *
 * The graph already resolves a type into the class-likes it names, because those are
 * the dependencies a signature commits to. That resolution loses the type: `?Invoice`
 * and `Invoice|null` resolve to the same one name, and `int|string` resolves to none
 * at all. A reader asking what a method returns wants the type, not its dependencies,
 * so the written form is kept beside them.
 *
 * It is kept as written rather than normalised. A type is part of the source's own
 * vocabulary, and rewriting it would answer a question the source did not ask.
 *
 * @visibility App\Analyzer
 */
final class TypeText
{
    /**
     * Writes a type node out as the text that declared it.
     *
     * A name is written as it stands, so a type imported under an alias reads as the
     * file writes it rather than as the analyser resolved it. Nullability is written
     * back with the leading question mark PHP uses for it, and unions and
     * intersections are joined by their own separators.
     *
     * @param null|Node $type The written type, or null when the declaration wrote none
     *
     * @example A simple type reads as it was written
     *     \App\Analyzer\Declaration\TypeText::of(new \PhpParser\Node\Identifier('int')) // => 'int'
     * @example A nullable type keeps the mark that makes it one
     *     $written = new \PhpParser\Node\NullableType(new \PhpParser\Node\Name('Invoice'));
     *     \App\Analyzer\Declaration\TypeText::of($written) // => '?Invoice'
     * @example A declaration that wrote no type says nothing
     *     \App\Analyzer\Declaration\TypeText::of(null) // => null
     *
     * @return null|string The type as written, or null when none was written
     */
    public static function of(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Identifier) {
            return $type->toString();
        }
        if ($type instanceof Name) {
            return $type->toString();
        }
        if ($type instanceof NullableType) {
            return '?'.(self::of($type->type) ?? '');
        }
        if ($type instanceof UnionType) {
            return implode('|', self::parts($type));
        }
        if ($type instanceof IntersectionType) {
            return implode('&', self::parts($type));
        }

        return null;
    }

    /**
     * Writes out the members of a union or an intersection.
     *
     * An intersection written inside a union is parenthesised, which is how PHP
     * writes the one case where a compound type contains another.
     *
     * @param IntersectionType|UnionType $type The compound type to write out
     *
     * @example A union reads as the separators that joined it
     *     $written = new \PhpParser\Node\UnionType([new \PhpParser\Node\Identifier('int'), new \PhpParser\Node\Identifier('string')]);
     *     \App\Analyzer\Declaration\TypeText::parts($written) // => ['int', 'string']
     *
     * @return list<string> The members as written, in source order
     */
    public static function parts(IntersectionType|UnionType $type): array
    {
        $written = [];
        foreach ($type->types as $member) {
            $text = self::of($member) ?? '';
            $written[] = $member instanceof IntersectionType ? '('.$text.')' : $text;
        }

        return $written;
    }
}
