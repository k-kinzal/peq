<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

final class TypeResolver
{
    /**
     * Recursively extracts all Name nodes from a type hint,
     * handling NullableType, UnionType, IntersectionType, and DNF types.
     *
     * @return list<Name>
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
                $names = array_merge($names, self::resolveNames($inner));
            }

            return $names;
        }

        if ($type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $inner) {
                $names = array_merge($names, self::resolveNames($inner));
            }

            return $names;
        }

        // Identifier (builtins like int, string, null, etc.) and other nodes
        return [];
    }
}
