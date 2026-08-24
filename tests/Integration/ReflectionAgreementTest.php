<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\AnalysedSources;
use Tests\Fixture\Analyzer\ReflectionCrossCheck;

/**
 * @internal
 */
#[CoversClass(PhpStanAnalyzer::class)]
#[Large]
final class ReflectionAgreementTest extends TestCase
{
    #[DataProvider('providerKindsReflectionCanConfirm')]
    public function testEveryRelationOfThatKindIsConfirmedByReflection(EdgeKind $kind): void
    {
        ReflectionCrossCheck::assertEdgeKindSoundness(AnalysedSources::everything(), $kind);
    }

    #[DataProvider('providerKindsReflectionCanConfirm')]
    public function testEveryRelationOfThatKindReflectionSeesIsInTheGraph(EdgeKind $kind): void
    {
        ReflectionCrossCheck::assertEdgeKindCompleteness(AnalysedSources::everything(), $kind);
    }

    /**
     * @return iterable<string, array{EdgeKind}>
     */
    public static function providerKindsReflectionCanConfirm(): iterable
    {
        yield 'extends' => [EdgeKind::DeclarationExtends];

        yield 'implements' => [EdgeKind::DeclarationImplements];

        yield 'trait use' => [EdgeKind::DeclarationTraitUse];

        yield 'method declaration' => [EdgeKind::DeclarationMethod];

        yield 'property declaration' => [EdgeKind::DeclarationProperty];

        yield 'constant declaration' => [EdgeKind::DeclarationConstant];

        yield 'enum case declaration' => [EdgeKind::DeclarationEnumCase];

        yield 'attribute' => [EdgeKind::Attribute];

        yield 'parameter type' => [EdgeKind::DeclarationTypeParameter];

        yield 'return type' => [EdgeKind::DeclarationTypeReturn];

        yield 'property type' => [EdgeKind::DeclarationTypeProperty];
    }
}
