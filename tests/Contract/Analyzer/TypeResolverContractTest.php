<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver;
use Eris\Generator;
use Eris\TestTrait;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * @internal
 *
 * Property-based contract test for TypeResolver::resolveNames().
 *
 * Generates random PHP type trees (respecting PHP's type grammar constraints)
 * and verifies two properties:
 * 1. All returned values are Name instances
 * 2. The count of returned Names matches the count of Name leaf nodes in the tree
 *
 * This grounds the case analysis in DeclarationEdgeContractTest's DataProviders,
 * proving that TypeResolver handles arbitrary type compositions correctly.
 */
final class TypeResolverContractTest extends TestCase
{
    use TestTrait;

    private const BUILTINS = ['int', 'string', 'float', 'bool', 'null', 'void', 'never', 'mixed', 'array', 'object', 'callable', 'iterable', 'self', 'parent', 'static'];

    #[Test]
    public function testAllReturnedValuesAreNameInstances(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $type = self::buildTypeTree($seed);
                $result = TypeResolver::resolveNames($type);

                foreach ($result as $i => $item) {
                    self::assertInstanceOf(
                        Name::class,
                        $item,
                        sprintf(
                            'Item at index %d is %s, expected Name (seed=%d)',
                            $i,
                            get_debug_type($item),
                            $seed,
                        ),
                    );
                }
            })
        ;
    }

    #[Test]
    public function testReturnedCountMatchesNameLeafCount(): void
    {
        $this->forAll(Generator\choose(1, 10000))
            ->then(function (int $seed): void {
                $type = self::buildTypeTree($seed);
                $result = TypeResolver::resolveNames($type);
                $expected = self::countNameLeaves($type);

                self::assertCount(
                    $expected,
                    $result,
                    sprintf(
                        'Expected %d Name nodes but got %d (seed=%d, type=%s)',
                        $expected,
                        count($result),
                        $seed,
                        $type === null ? 'null' : get_debug_type($type),
                    ),
                );
            })
        ;
    }

    /**
     * Deterministically builds a random PHP type tree from a seed.
     */
    private static function buildTypeTree(int $seed): ?Node
    {
        $rng = new Randomizer(new Mt19937($seed));
        $counter = 0;

        return self::buildTypeNode($counter, $rng, maxDepth: 4);
    }

    /**
     * Recursively generates a type node respecting PHP's type grammar.
     *
     * Choices at each level:
     * 0 = null, 1 = Identifier, 2 = Name, 3 = FullyQualified Name,
     * 4 = NullableType, 5 = UnionType, 6 = IntersectionType
     */
    private static function buildTypeNode(int &$counter, Randomizer $rng, int $maxDepth): ?Node
    {
        if ($maxDepth <= 0) {
            return self::buildLeafOrNull($counter, $rng);
        }

        return match ($rng->getInt(0, 6)) {
            0 => null,
            1 => new Identifier(self::pickBuiltin($rng)),
            2 => self::makeName($counter),
            3 => new Name\FullyQualified(self::makeNameParts($counter)),
            4 => new NullableType(self::buildLeafNode($counter, $rng)),
            5 => self::buildUnionType($counter, $rng, $maxDepth - 1),
            6 => self::buildIntersectionType($counter, $rng),
            default => null,
        };
    }

    /**
     * At max depth, produce only a leaf (Name, Identifier) or null.
     */
    private static function buildLeafOrNull(int &$counter, Randomizer $rng): ?Node
    {
        return match ($rng->getInt(0, 2)) {
            0 => null,
            1 => new Identifier(self::pickBuiltin($rng)),
            2 => self::makeName($counter),
            default => null,
        };
    }

    /**
     * Produces a Name or Identifier leaf (never null, never composite).
     * Suitable for NullableType wrapping and IntersectionType members.
     */
    private static function buildLeafNode(int &$counter, Randomizer $rng): Identifier|Name
    {
        return $rng->getInt(0, 1) === 0
            ? new Identifier(self::pickBuiltin($rng))
            : self::makeName($counter);
    }

    /**
     * Builds a UnionType with 2-4 members.
     * Members can be Name, Identifier, or IntersectionType (DNF).
     */
    private static function buildUnionType(int &$counter, Randomizer $rng, int $maxDepth): UnionType
    {
        $count = $rng->getInt(2, 4);
        $types = [];
        for ($i = 0; $i < $count; ++$i) {
            $types[] = self::buildUnionMember($counter, $rng, $maxDepth);
        }

        return new UnionType($types);
    }

    /**
     * A union member: Name, Identifier, or IntersectionType.
     */
    private static function buildUnionMember(int &$counter, Randomizer $rng, int $maxDepth): Identifier|IntersectionType|Name
    {
        if ($maxDepth <= 0) {
            return self::buildLeafNode($counter, $rng);
        }

        return match ($rng->getInt(0, 2)) {
            0 => new Identifier(self::pickBuiltin($rng)),
            1 => self::makeName($counter),
            2 => self::buildIntersectionType($counter, $rng),
            default => self::buildLeafNode($counter, $rng),
        };
    }

    /**
     * Builds an IntersectionType with 2-3 members (Name or Identifier only).
     */
    private static function buildIntersectionType(int &$counter, Randomizer $rng): IntersectionType
    {
        $count = $rng->getInt(2, 3);
        $types = [];
        for ($i = 0; $i < $count; ++$i) {
            $types[] = self::buildLeafNode($counter, $rng);
        }

        return new IntersectionType($types);
    }

    private static function makeName(int &$counter): Name
    {
        ++$counter;

        return new Name('Generated\Type'.$counter);
    }

    /**
     * @return list<string>
     */
    private static function makeNameParts(int &$counter): array
    {
        ++$counter;

        return ['Generated', 'Type'.$counter];
    }

    private static function pickBuiltin(Randomizer $rng): string
    {
        return self::BUILTINS[$rng->getInt(0, count(self::BUILTINS) - 1)];
    }

    /**
     * Independent oracle: recursively counts Name leaf nodes in a type tree.
     * This is intentionally independent of TypeResolver's implementation.
     */
    private static function countNameLeaves(?Node $type): int
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

        // Identifier and anything else
        return 0;
    }
}
