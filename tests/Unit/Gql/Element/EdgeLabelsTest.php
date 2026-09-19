<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\EdgeKind;
use App\Gql\Element\EdgeLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EdgeLabels::class)]
#[UsesClass(EdgeKind::class)]
#[Small]
final class EdgeLabelsTest extends TestCase
{
    #[DataProvider('providerKindsAndTheLabelsTheyCarry')]
    public function testOfReadsWhatAQueryCallsARelationAndWhatItBelongsTo(EdgeKind $kind, string $labels): void
    {
        self::assertSame($labels, implode(',', EdgeLabels::of($kind)));
    }

    /**
     * @return iterable<string, array{EdgeKind, string}>
     */
    public static function providerKindsAndTheLabelsTheyCarry(): iterable
    {
        yield 'a call to a function' => [EdgeKind::FunctionCall, 'functionCall,call,usage'];

        yield 'a call to a method' => [EdgeKind::MethodCall, 'methodCall,call,usage'];

        yield 'a call to a static method' => [EdgeKind::StaticCall, 'staticCall,call,usage'];

        yield 'making one of something' => [EdgeKind::Instantiation, 'instantiation,usage'];

        yield 'reading a property' => [EdgeKind::PropertyAccess, 'propertyAccess,usage'];

        yield 'reading a static property' => [EdgeKind::StaticPropertyAccess, 'staticPropertyAccess,usage'];

        yield 'reading a constant' => [EdgeKind::ConstFetch, 'constFetch,usage'];

        yield 'asking what something is' => [EdgeKind::Instanceof, 'instanceOf,usage'];

        yield 'catching something' => [EdgeKind::Catch, 'catches,usage'];

        yield 'declaring a method' => [EdgeKind::DeclarationMethod, 'declaresMethod,declares,declaration'];

        yield 'declaring a property' => [EdgeKind::DeclarationProperty, 'declaresProperty,declares,declaration'];

        yield 'declaring a constant' => [EdgeKind::DeclarationConstant, 'declaresConstant,declares,declaration'];

        yield 'declaring an enum case' => [EdgeKind::DeclarationEnumCase, 'declaresEnumCase,declares,declaration'];

        yield 'naming a type in a parameter' => [EdgeKind::DeclarationTypeParameter, 'parameterType,signatureType,declaration'];

        yield 'naming a type as a result' => [EdgeKind::DeclarationTypeReturn, 'returnType,signatureType,declaration'];

        yield 'naming a type on a property' => [EdgeKind::DeclarationTypeProperty, 'propertyType,declaration'];

        yield 'using a trait' => [EdgeKind::DeclarationTraitUse, 'traitUse,declaration'];

        yield 'extending a class-like' => [EdgeKind::DeclarationExtends, 'extends,declaration'];

        yield 'implementing an interface' => [EdgeKind::DeclarationImplements, 'implements,declaration'];

        yield 'writing an attribute' => [EdgeKind::Attribute, 'attribute,declaration'];

        yield 'a relation read the other way' => [EdgeKind::UsedBy, 'usedBy,inverse'];

        yield 'a declaration read the other way' => [EdgeKind::DeclaredIn, 'declaredIn,inverse'];
    }

    public function testNameReadsARelationTheWayASentenceAboutCodeWould(): void
    {
        self::assertSame('declaresMethod', EdgeLabels::name(EdgeKind::DeclarationMethod));
    }

    public function testNameCoversEveryKindOfRelationTheGraphRecords(): void
    {
        $named = array_map(EdgeLabels::name(...), EdgeKind::cases());

        self::assertCount(count(EdgeKind::cases()), array_unique($named));
    }

    public function testBelongsToGathersEveryCallUnderOneFamily(): void
    {
        self::assertSame(['call', 'usage'], EdgeLabels::belongsTo(EdgeKind::StaticCall));
    }

    public function testAllOffersTheFamiliesAPatternSelectsBy(): void
    {
        self::assertContains('call', EdgeLabels::all());
    }

    public function testAllLeavesOutTheReadingsNoPatternMeets(): void
    {
        self::assertNotContains('usedBy', EdgeLabels::all());
    }

    public function testAllOffersEachLabelOnlyOnce(): void
    {
        self::assertSame(array_unique(EdgeLabels::all()), EdgeLabels::all());
    }
}
