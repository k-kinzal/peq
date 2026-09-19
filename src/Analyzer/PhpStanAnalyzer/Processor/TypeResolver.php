<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\QualifiedName;
use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * Reads the class-like names out of a written type.
 *
 * A type may be a single name, a nullable name, or a union or intersection of
 * several, and only the names that could refer to a declaration are of interest. This
 * flattens whichever of those was written into the list of names it mentions.
 *
 * @visibility parent
 */
final class TypeResolver
{
    /**
     * Reads the class-like types a written type refers to, with their positions.
     *
     * Builtin names are left out: they name nothing a codebase declares, so there is
     * no node for a relation to point at.
     *
     * @param null|Node $type The written type, or null when none was written
     * @param string    $file The file the type is written in
     *
     * @return list<TypeReference> One reference per class-like the type names
     */
    public static function references(?Node $type, string $file): array
    {
        $references = [];
        foreach (self::resolveNames($type) as $name) {
            $written = $name->toString();
            if ((new QualifiedName($written))->isBuiltinType()) {
                continue;
            }

            $references[] = new TypeReference(
                new ClassNode(ClassNodeId::of($written), false, null),
                new FileMeta($file, $name->getStartLine(), 1),
            );
        }

        return $references;
    }

    /**
     * Reads every class-like name a written type mentions.
     *
     * A nullable type, a union, an intersection and a disjunctive normal form type
     * are all read through to the names inside them, so a signature contributes one
     * relation per type it could actually refer to.
     *
     * @param null|Node $type The written type, or null when none was written
     *
     * @return list<Name> The names the type mentions
     */
    public static function resolveNames(?Node $type): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof Name) {
            return [$type];
        }

        if ($type instanceof NullableType) {
            return self::resolveNames($type->type);
        }

        if ($type instanceof UnionType) {
            $names = [];
            foreach ($type->types as $inner) {
                array_push($names, ...self::resolveNames($inner));
            }

            return $names;
        }

        if ($type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $inner) {
                array_push($names, ...self::resolveNames($inner));
            }

            return $names;
        }

        return [];
    }
}
