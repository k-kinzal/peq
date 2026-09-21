<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use PhpParser\Node\Attribute;

/**
 * Reads one attribute off a declaration, under the name it resolves to.
 *
 * Resolving the written name into a fully qualified one is the analyser's job and
 * differs between the ways peq reads sources, so the resolved name is handed in
 * rather than worked out here. What is worked out here is the rest: the arguments,
 * written back as the text that gave them.
 *
 * @visibility App\Analyzer
 */
final class WrittenAttribute
{
    /**
     * Reads one attribute as it was written on a declaration.
     *
     * @param Attribute $attribute    The attribute as it was written
     * @param string    $resolvedName The fully qualified name it resolves to
     *
     * @example An attribute keeps the arguments it was given
     *     $written = new \PhpParser\Node\Attribute(
     *         new \PhpParser\Node\Name('Route'),
     *         [new \PhpParser\Node\Arg(new \PhpParser\Node\Scalar\String_('/users'))],
     *     );
     *     \App\Analyzer\Declaration\WrittenAttribute::usage($written, 'App\\Http\\Route')->toString() // => "App\\Http\\Route('/users')"
     *
     * @return AttributeUsage The attribute, under the name it resolves to
     */
    public static function usage(Attribute $attribute, string $resolvedName): AttributeUsage
    {
        $arguments = [];
        foreach ($attribute->args as $argument) {
            $arguments[] = ExpressionText::argument($argument);
        }

        return new AttributeUsage($resolvedName, $arguments);
    }
}
