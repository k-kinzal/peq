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
    /**
     * @param list<string> $labels
     */
    #[DataProvider('providerKindsAndTheLabelsTheyCarry')]
    public function testOfReadsWhatAQueryCallsARelationAndWhatItBelongsTo(EdgeKind $kind, array $labels): void
    {
        self::assertSame($labels, EdgeLabels::of($kind));
    }

    /**
     * @return iterable<string, array{EdgeKind, list<string>}>
     */
    public static function providerKindsAndTheLabelsTheyCarry(): iterable
    {
        yield 'a call to a function' => [EdgeKind::FunctionCall, ['functionCall', 'call', 'usage']];

        yield 'a call to a method' => [EdgeKind::MethodCall, ['methodCall', 'call', 'usage']];

        yield 'a call to a static method' => [EdgeKind::StaticCall, ['staticCall', 'call', 'usage']];

        yield 'making one of something' => [EdgeKind::Instantiation, ['instantiation', 'usage']];

        yield 'reading a property' => [EdgeKind::PropertyAccess, ['propertyAccess', 'usage']];

        yield 'reading a static property' => [EdgeKind::StaticPropertyAccess, ['staticPropertyAccess', 'usage']];

        yield 'reading a constant' => [EdgeKind::ConstFetch, ['constFetch', 'usage']];

        yield 'asking what something is' => [EdgeKind::Instanceof, ['instanceOf', 'usage']];

        yield 'catching something' => [EdgeKind::Catch, ['catches', 'usage']];

        yield 'declaring a method' => [EdgeKind::DeclarationMethod, ['declaresMethod', 'declares', 'declaration']];

        yield 'declaring a property' => [EdgeKind::DeclarationProperty, ['declaresProperty', 'declares', 'declaration']];

        yield 'declaring a constant' => [EdgeKind::DeclarationConstant, ['declaresConstant', 'declares', 'declaration']];

        yield 'declaring an enum case' => [EdgeKind::DeclarationEnumCase, ['declaresEnumCase', 'declares', 'declaration']];

        yield 'naming a type in a parameter' => [EdgeKind::DeclarationTypeParameter, ['parameterType', 'signatureType', 'declaration']];

        yield 'naming a type as a result' => [EdgeKind::DeclarationTypeReturn, ['returnType', 'signatureType', 'declaration']];

        yield 'naming a type on a property' => [EdgeKind::DeclarationTypeProperty, ['propertyType', 'declaration']];

        yield 'using a trait' => [EdgeKind::DeclarationTraitUse, ['traitUse', 'declaration']];

        yield 'extending a class-like' => [EdgeKind::DeclarationExtends, ['extends', 'declaration']];

        yield 'implementing an interface' => [EdgeKind::DeclarationImplements, ['implements', 'declaration']];

        yield 'writing an attribute' => [EdgeKind::Attribute, ['attribute', 'declaration']];

        yield 'a relation read the other way' => [EdgeKind::UsedBy, ['usedBy', 'inverse']];

        yield 'a declaration read the other way' => [EdgeKind::DeclaredIn, ['declaredIn', 'inverse']];
    }

    public function testNameReadsARelationTheWayASentenceAboutCodeWould(): void
    {
        self::assertSame('declaresMethod', EdgeLabels::name(EdgeKind::DeclarationMethod));
    }

    public function testNameGivesEveryKindOfRelationTheGraphRecordsANameOfItsOwn(): void
    {
        self::assertSame(
            [
                'functionCall',
                'methodCall',
                'staticCall',
                'instantiation',
                'propertyAccess',
                'staticPropertyAccess',
                'constFetch',
                'traitUse',
                'extends',
                'implements',
                'declaresMethod',
                'declaresProperty',
                'declaresConstant',
                'declaresEnumCase',
                'parameterType',
                'returnType',
                'propertyType',
                'attribute',
                'instanceOf',
                'catches',
                'usedBy',
                'declaredIn',
            ],
            array_map(EdgeLabels::name(...), EdgeKind::cases()),
        );
    }

    public function testBelongsToGathersEveryCallUnderOneFamily(): void
    {
        self::assertSame(['call', 'usage'], EdgeLabels::belongsTo(EdgeKind::StaticCall));
    }

    public function testBelongsToGathersEveryReadingTheOtherWayUnderOneFamily(): void
    {
        self::assertSame(['inverse'], EdgeLabels::belongsTo(EdgeKind::UsedBy));
    }

    public function testAllOffersEveryLabelAPatternMeetsOnceAndLeavesOutTheReadingsNoPatternMeets(): void
    {
        self::assertSame(
            [
                'functionCall',
                'call',
                'usage',
                'methodCall',
                'staticCall',
                'instantiation',
                'propertyAccess',
                'staticPropertyAccess',
                'constFetch',
                'traitUse',
                'declaration',
                'extends',
                'implements',
                'declaresMethod',
                'declares',
                'declaresProperty',
                'declaresConstant',
                'declaresEnumCase',
                'parameterType',
                'signatureType',
                'returnType',
                'propertyType',
                'attribute',
                'instanceOf',
                'catches',
            ],
            EdgeLabels::all(),
        );
    }
}
