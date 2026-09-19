<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BuiltinNodeId::class)]
#[CoversClass(ClassNodeId::class)]
#[CoversClass(ConstantNodeId::class)]
#[CoversClass(EnumCaseNodeId::class)]
#[CoversClass(EnumNodeId::class)]
#[CoversClass(FunctionNodeId::class)]
#[CoversClass(InterfaceNodeId::class)]
#[CoversClass(MethodNodeId::class)]
#[CoversClass(PropertyNodeId::class)]
#[CoversClass(TraitNodeId::class)]
#[CoversClass(UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class NodeIdTest extends TestCase
{
    /**
     * @param NodeId<Node> $id
     */
    #[DataProvider('providerEveryKindOfIdentifier')]
    public function testToStringIsTheNameASymbolIsWrittenAs(NodeId $id, string $written): void
    {
        self::assertSame($written, $id->toString());
    }

    /**
     * @param NodeId<Node> $id
     */
    #[DataProvider('providerEveryKindOfIdentifier')]
    public function testToStringAnswersTheSameWayEveryTime(NodeId $id, string $written): void
    {
        self::assertSame($id->toString(), $id->toString());
        self::assertSame($written, $id->toString());
    }

    /**
     * @return iterable<string, array{NodeId<Node>, string}>
     */
    public static function providerEveryKindOfIdentifier(): iterable
    {
        yield 'class' => [ClassNodeId::of('App\Domain\Invoice'), 'App\Domain\Invoice'];

        yield 'interface' => [InterfaceNodeId::of('App\Domain\Payable'), 'App\Domain\Payable'];

        yield 'trait' => [TraitNodeId::of('App\Domain\Timestamped'), 'App\Domain\Timestamped'];

        yield 'enum' => [EnumNodeId::of('App\Domain\InvoiceState'), 'App\Domain\InvoiceState'];

        yield 'enum case' => [EnumCaseNodeId::of('App\Domain\InvoiceState', 'OPEN'), 'App\Domain\InvoiceState::OPEN'];

        yield 'method' => [MethodNodeId::of('App\Domain\Invoice', 'total'), 'App\Domain\Invoice::total'];

        yield 'property' => [PropertyNodeId::of('App\Domain\Invoice', 'lines'), 'App\Domain\Invoice::lines'];

        yield 'constant' => [ConstantNodeId::of('App\Domain\Invoice', 'MAX_ITEMS'), 'App\Domain\Invoice::MAX_ITEMS'];

        yield 'function' => [FunctionNodeId::of('App\Domain\formatMoney'), 'App\Domain\formatMoney'];

        yield 'builtin' => [BuiltinNodeId::of('int'), 'int'];

        yield 'unresolved' => [new UnknownNodeId('App\Domain\Missing'), 'App\Domain\Missing'];
    }

    public function testAMemberIsNamedApartFromTheSymbolThatDeclaresIt(): void
    {
        self::assertNotSame(
            ClassNodeId::of('App\Domain\Invoice')->toString(),
            MethodNodeId::of('App\Domain\Invoice', 'total')->toString(),
        );
    }

    public function testTwoKindsOfSymbolWithTheSameNameShareAnIdentifier(): void
    {
        self::assertSame(
            ClassNodeId::of('App\Domain\Invoice')->toString(),
            InterfaceNodeId::of('App\Domain\Invoice')->toString(),
        );
    }
}
