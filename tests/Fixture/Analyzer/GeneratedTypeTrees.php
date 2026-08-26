<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * Type expressions drawn at random, and the count of names written in them.
 *
 * A type position can hold a name, a builtin, a nullable, a union of either, or an
 * intersection inside a union, and the resolver has to answer the same question of
 * all of them. Drawing them from a seed covers shapes nobody would think to write
 * down, and counting the names in the drawn tree is what gives the check something
 * to compare the resolver's answer against.
 */
final class GeneratedTypeTrees
{
    /**
     * The names PHP resolves itself, which a drawn tree mixes in with declared ones.
     *
     * @var list<string>
     */
    private const BUILTINS = ['int', 'string', 'float', 'bool', 'null', 'void', 'never', 'mixed', 'array', 'object', 'callable', 'iterable', 'self', 'parent', 'static'];

    /**
     * Names each seed the resolver is checked over.
     *
     * @return iterable<string, array{int}> The seeds, one per case
     */
    public static function seeds(): iterable
    {
        foreach ([1, 3, 17, 42, 128, 777, 1234, 4321, 9999, 31337] as $seed) {
            yield sprintf('seed %d', $seed) => [$seed];
        }
    }

    /**
     * Draws the type expression one seed describes.
     *
     * @param int $seed The seed to draw with
     *
     * @return null|Node The drawn type, which may be no type at all
     */
    public static function buildTypeTree(int $seed): ?Node
    {
        mt_srand($seed);
        $counter = 0;

        return self::buildTypeNode($counter, maxDepth: 4);
    }

    /**
     * Draws a type expression of any shape, down to a depth.
     *
     * @param int $counter  How many names have been drawn so far
     * @param int $maxDepth How much deeper the expression may nest
     *
     * @return null|Node The drawn type
     */
    public static function buildTypeNode(int &$counter, int $maxDepth): ?Node
    {
        if ($maxDepth <= 0) {
            return self::buildLeafOrNull($counter);
        }

        return match (mt_rand(0, 6)) {
            0 => null,
            1 => new Identifier(self::pickBuiltin()),
            2 => self::makeName($counter),
            3 => new Name\FullyQualified(self::makeNameParts($counter)),
            4 => new NullableType(self::buildLeafNode($counter)),
            5 => self::buildUnionType($counter, $maxDepth - 1),
            6 => self::buildIntersectionType($counter),
            default => null,
        };
    }

    /**
     * Draws a type that nests no further.
     *
     * @param int $counter How many names have been drawn so far
     *
     * @return null|Node The drawn type
     */
    public static function buildLeafOrNull(int &$counter): ?Node
    {
        return match (mt_rand(0, 2)) {
            0 => null,
            1 => new Identifier(self::pickBuiltin()),
            2 => self::makeName($counter),
            default => null,
        };
    }

    /**
     * Draws a builtin or a declared name.
     *
     * @param int $counter How many names have been drawn so far
     *
     * @return Identifier|Name The drawn type
     */
    public static function buildLeafNode(int &$counter): Identifier|Name
    {
        return mt_rand(0, 1) === 0
            ? new Identifier(self::pickBuiltin())
            : self::makeName($counter);
    }

    /**
     * Draws a union of two to four members.
     *
     * @param int $counter  How many names have been drawn so far
     * @param int $maxDepth How much deeper the expression may nest
     *
     * @return UnionType The drawn union
     */
    public static function buildUnionType(int &$counter, int $maxDepth): UnionType
    {
        $count = mt_rand(2, 4);
        $types = [];
        for ($i = 0; $i < $count; ++$i) {
            $types[] = self::buildUnionMember($counter, $maxDepth);
        }

        return new UnionType($types);
    }

    /**
     * Draws one member of a union.
     *
     * @param int $counter  How many names have been drawn so far
     * @param int $maxDepth How much deeper the expression may nest
     *
     * @return Identifier|IntersectionType|Name The drawn member
     */
    public static function buildUnionMember(int &$counter, int $maxDepth): Identifier|IntersectionType|Name
    {
        if ($maxDepth <= 0) {
            return self::buildLeafNode($counter);
        }

        return match (mt_rand(0, 2)) {
            0 => new Identifier(self::pickBuiltin()),
            1 => self::makeName($counter),
            2 => self::buildIntersectionType($counter),
            default => self::buildLeafNode($counter),
        };
    }

    /**
     * Draws an intersection of two or three names.
     *
     * @param int $counter How many names have been drawn so far
     *
     * @return IntersectionType The drawn intersection
     */
    public static function buildIntersectionType(int &$counter): IntersectionType
    {
        $count = mt_rand(2, 3);
        $types = [];
        for ($i = 0; $i < $count; ++$i) {
            $types[] = self::buildLeafNode($counter);
        }

        return new IntersectionType($types);
    }

    /**
     * Draws a declared name nobody else has been given.
     *
     * @param int $counter How many names have been drawn so far
     *
     * @return Name The drawn name
     */
    public static function makeName(int &$counter): Name
    {
        ++$counter;

        return new Name('Generated\Type'.$counter);
    }

    /**
     * @return list<string>
     */
    /**
     * Draws the segments of a fully qualified declared name.
     *
     * @param int $counter How many names have been drawn so far
     *
     * @return list<string> The segments
     */
    public static function makeNameParts(int &$counter): array
    {
        ++$counter;

        return ['Generated', 'Type'.$counter];
    }

    /**
     * Picks one of the names PHP resolves itself.
     *
     * @return string The name
     */
    public static function pickBuiltin(): string
    {
        return self::BUILTINS[mt_rand(0, count(self::BUILTINS) - 1)];
    }

    /**
     * Counts the declared names written anywhere in a type expression.
     *
     * @param null|Node $type The type expression
     *
     * @return int How many names it writes
     */
    public static function countNameLeaves(?Node $type): int
    {
        if ($type === null) {
            return 0;
        }

        if ($type instanceof Name) {
            return 1;
        }

        if ($type instanceof NullableType) {
            return self::countNameLeaves($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $count = 0;
            foreach ($type->types as $inner) {
                $count += self::countNameLeaves($inner);
            }

            return $count;
        }

        return 0;
    }
}
