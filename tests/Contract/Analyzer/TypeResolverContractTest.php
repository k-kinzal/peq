<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\PhpStanAnalyzer\Processor\TypeResolver;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GeneratedTypeTrees;

/**
 * @internal
 */
#[CoversClass(TypeResolver::class)]
#[Medium]
final class TypeResolverContractTest extends TestCase
{
    #[DataProviderExternal(GeneratedTypeTrees::class, 'seeds')]
    public function testResolveNamesReportsOnlyNamesThatWereWritten(int $seed): void
    {
        $resolved = TypeResolver::resolveNames(GeneratedTypeTrees::buildTypeTree($seed));

        $written = array_map(static fn (Name $name): string => $name->toString(), $resolved);

        self::assertSame($written, array_values(array_filter($written, static fn (string $name): bool => $name !== '')));
    }

    #[DataProviderExternal(GeneratedTypeTrees::class, 'seeds')]
    public function testResolveNamesReportsOneNameForEachOneTheTypeWrites(int $seed): void
    {
        $type = GeneratedTypeTrees::buildTypeTree($seed);

        self::assertCount(GeneratedTypeTrees::countNameLeaves($type), TypeResolver::resolveNames($type));
    }
}
