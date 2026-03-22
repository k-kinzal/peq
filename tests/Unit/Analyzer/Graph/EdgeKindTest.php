<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\EdgeKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EdgeKindTest extends TestCase
{
    #[Test]
    #[DataProvider('provideInvertCases')]
    public function testInvertReturnsExpectedEdgeKind(EdgeKind $edgeKind, EdgeKind $expected): void
    {
        $result = $edgeKind->invert();

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{EdgeKind, EdgeKind}>
     */
    public static function provideInvertCases(): array
    {
        return [
            'FunctionCall inverts to UsedBy' => [EdgeKind::FunctionCall, EdgeKind::UsedBy],
            'MethodCall inverts to UsedBy' => [EdgeKind::MethodCall, EdgeKind::UsedBy],
            'StaticCall inverts to UsedBy' => [EdgeKind::StaticCall, EdgeKind::UsedBy],
            'Instantiation inverts to UsedBy' => [EdgeKind::Instantiation, EdgeKind::UsedBy],
            'PropertyAccess inverts to UsedBy' => [EdgeKind::PropertyAccess, EdgeKind::UsedBy],
            'StaticPropertyAccess inverts to UsedBy' => [EdgeKind::StaticPropertyAccess, EdgeKind::UsedBy],
            'ConstFetch inverts to UsedBy' => [EdgeKind::ConstFetch, EdgeKind::UsedBy],
            'DeclarationTraitUse inverts to DeclaredIn' => [EdgeKind::DeclarationTraitUse, EdgeKind::DeclaredIn],
            'DeclarationExtends inverts to DeclaredIn' => [EdgeKind::DeclarationExtends, EdgeKind::DeclaredIn],
            'DeclarationImplements inverts to DeclaredIn' => [EdgeKind::DeclarationImplements, EdgeKind::DeclaredIn],
            'DeclarationMethod inverts to DeclaredIn' => [EdgeKind::DeclarationMethod, EdgeKind::DeclaredIn],
            'DeclarationProperty inverts to DeclaredIn' => [EdgeKind::DeclarationProperty, EdgeKind::DeclaredIn],
            'DeclarationConstant inverts to DeclaredIn' => [EdgeKind::DeclarationConstant, EdgeKind::DeclaredIn],
            'DeclarationTypeParameter inverts to DeclaredIn' => [EdgeKind::DeclarationTypeParameter, EdgeKind::DeclaredIn],
            'DeclarationTypeReturn inverts to DeclaredIn' => [EdgeKind::DeclarationTypeReturn, EdgeKind::DeclaredIn],
            'DeclarationTypeProperty inverts to DeclaredIn' => [EdgeKind::DeclarationTypeProperty, EdgeKind::DeclaredIn],
            'DeclarationEnumCase inverts to DeclaredIn' => [EdgeKind::DeclarationEnumCase, EdgeKind::DeclaredIn],
            'Attribute inverts to UsedBy' => [EdgeKind::Attribute, EdgeKind::UsedBy],
            'Instanceof inverts to UsedBy' => [EdgeKind::Instanceof, EdgeKind::UsedBy],
            'Catch inverts to UsedBy' => [EdgeKind::Catch, EdgeKind::UsedBy],
        ];
    }

    #[Test]
    public function testInvertThrowsExceptionForUsedBy(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot reverse a reversed edge');

        EdgeKind::UsedBy->invert();
    }

    #[Test]
    public function testInvertThrowsExceptionForDeclaredIn(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot reverse a reversed edge');

        EdgeKind::DeclaredIn->invert();
    }
}
