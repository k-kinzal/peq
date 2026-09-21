<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\Element\NodeProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\SampleGraph;

/**
 * @internal
 */
#[CoversClass(NodeProperties::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(QualifiedName::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class NodePropertiesTest extends TestCase
{
    public function testOfOffersEverythingAQueryCanAskARoutedMethod(): void
    {
        self::assertEquals(
            [
                'id' => new StringDatum('App\Http\Controller::show'),
                'kind' => new StringDatum('method'),
                'resolved' => new BooleanDatum(true),
                'name' => new StringDatum('show'),
                'namespace' => new StringDatum('App\Http'),
                'owner' => new StringDatum('App\Http\Controller'),
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(20),
                'column' => new IntegerDatum(5),
                'static' => new BooleanDatum(false),
                'abstract' => new BooleanDatum(false),
                'final' => new BooleanDatum(false),
                'readonly' => new BooleanDatum(false),
                'deprecated' => new BooleanDatum(false),
                'attributes' => new ListDatum([new StringDatum('App\Http\Route')]),
                'visibility' => new StringDatum('public'),
                'signature' => new StringDatum('(int $id): string'),
                'parameters' => new ListDatum([new StringDatum('id')]),
                'parameterTypes' => new ListDatum([new StringDatum('int')]),
                'parameterCount' => new IntegerDatum(1),
                'returnType' => new StringDatum('string'),
            ],
            NodeProperties::of(SampleGraph::show()),
        );
    }

    public function testOfLeavesOutEverythingTheSourceSaysNothingAbout(): void
    {
        self::assertEquals(
            [
                'id' => new StringDatum('App\Http\Kernel'),
                'kind' => new StringDatum('class'),
                'resolved' => new BooleanDatum(true),
                'name' => new StringDatum('Kernel'),
                'namespace' => new StringDatum('App\Http'),
                'file' => new StringDatum('/project/src/Http/Kernel.php'),
                'fileName' => new StringDatum('Kernel.php'),
                'line' => new IntegerDatum(7),
                'column' => new IntegerDatum(5),
            ],
            NodeProperties::of(SampleGraph::kernel()),
        );
    }

    public function testOfOffersNothingDeclaredForASymbolAnalysisOnlyEverSawReferredTo(): void
    {
        self::assertEquals(
            [
                'id' => new StringDatum('App\Missing'),
                'kind' => new StringDatum('unknown'),
                'resolved' => new BooleanDatum(false),
                'name' => new StringDatum('Missing'),
                'namespace' => new StringDatum('App'),
            ],
            NodeProperties::of(new UnknownNode(new UnknownNodeId('App\Missing'))),
        );
    }

    public function testAllNamesEveryPropertyASymbolCanCarryAndWhatKindOfValueItHolds(): void
    {
        self::assertSame(
            [
                'id' => 'STRING',
                'kind' => 'STRING',
                'name' => 'STRING',
                'namespace' => 'STRING',
                'owner' => 'STRING',
                'resolved' => 'BOOL',
                'file' => 'STRING',
                'fileName' => 'STRING',
                'line' => 'INT64',
                'column' => 'INT64',
                'visibility' => 'STRING',
                'static' => 'BOOL',
                'abstract' => 'BOOL',
                'final' => 'BOOL',
                'readonly' => 'BOOL',
                'deprecated' => 'BOOL',
                'attributes' => 'LIST<STRING>',
                'type' => 'STRING',
                'value' => 'STRING',
                'signature' => 'STRING',
                'returnType' => 'STRING',
                'parameters' => 'LIST<STRING>',
                'parameterTypes' => 'LIST<STRING>',
                'parameterCount' => 'INT64',
            ],
            NodeProperties::all(),
        );
    }

    #[DataProvider('providerEverySymbolOfTheSampleCodebase')]
    public function testAllNamesEveryPropertyASymbolOfTheSampleCodebaseCarries(Node $node): void
    {
        self::assertSame([], array_values(array_diff(array_keys(NodeProperties::of($node)), array_keys(NodeProperties::all()))));
    }

    /**
     * @return iterable<string, array{Node}>
     */
    public static function providerEverySymbolOfTheSampleCodebase(): iterable
    {
        foreach (SampleGraph::analysed()->nodes() as $node) {
            yield $node->id()->toString() => [$node];
        }
    }

    public function testNamingSplitsAMemberIntoWhatDeclaresItAndWhatItIsCalled(): void
    {
        self::assertEquals(
            [
                'name' => new StringDatum('total'),
                'namespace' => new StringDatum('App\Domain'),
                'owner' => new StringDatum('App\Domain\Invoice'),
            ],
            NodeProperties::naming('App\Domain\Invoice::total'),
        );
    }

    public function testNamingSplitsAClassLikeIntoItsNamespaceAndItsShortNameAndNothingToBeDeclaredBy(): void
    {
        self::assertEquals(
            ['name' => new StringDatum('Invoice'), 'namespace' => new StringDatum('App\Domain')],
            NodeProperties::naming('App\Domain\Invoice'),
        );
    }

    public function testLocationReadsWhereASymbolIsWritten(): void
    {
        self::assertEquals(
            [
                'file' => new StringDatum('/project/src/Http/Controller.php'),
                'fileName' => new StringDatum('Controller.php'),
                'line' => new IntegerDatum(10),
                'column' => new IntegerDatum(5),
            ],
            NodeProperties::location(SampleGraph::controller()),
        );
    }

    public function testLocationReadsNothingOfASymbolWrittenNowhereItKnowsOf(): void
    {
        self::assertSame([], NodeProperties::location(new UnknownNode(new UnknownNodeId('App\Missing'))));
    }

    public function testDeclaredOffersEveryKeywordWhetherOrNotItWasWritten(): void
    {
        self::assertEquals(
            [
                'static' => new BooleanDatum(false),
                'abstract' => new BooleanDatum(false),
                'final' => new BooleanDatum(false),
                'readonly' => new BooleanDatum(false),
                'deprecated' => new BooleanDatum(false),
                'attributes' => new ListDatum([]),
            ],
            NodeProperties::declared(new SymbolDeclaration()),
        );
    }

    public function testDeclaredOffersTheKeywordsThatWereWritten(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(static: true), deprecated: true);

        self::assertEquals(
            [
                'static' => new BooleanDatum(true),
                'abstract' => new BooleanDatum(false),
                'final' => new BooleanDatum(false),
                'readonly' => new BooleanDatum(false),
                'deprecated' => new BooleanDatum(true),
                'attributes' => new ListDatum([]),
            ],
            NodeProperties::declared($declared),
        );
    }

    public function testDeclaredOffersTheValueAConstantIsGivenAndTheTypeAPropertyIsGiven(): void
    {
        self::assertEquals(
            [
                'static' => new BooleanDatum(false),
                'abstract' => new BooleanDatum(false),
                'final' => new BooleanDatum(false),
                'readonly' => new BooleanDatum(false),
                'deprecated' => new BooleanDatum(false),
                'attributes' => new ListDatum([]),
                'type' => new StringDatum('int'),
                'value' => new StringDatum('1'),
            ],
            NodeProperties::declared(new SymbolDeclaration(type: 'int', value: '1')),
        );
    }

    public function testSignatureOffersTheShapeACallablesCallersWereWrittenAgainst(): void
    {
        $declared = new SymbolDeclaration(signature: new Signature([new Parameter('amount', 'int')], 'void'));

        self::assertEquals(
            [
                'signature' => new StringDatum('(int $amount): void'),
                'parameters' => new ListDatum([new StringDatum('amount')]),
                'parameterTypes' => new ListDatum([new StringDatum('int')]),
                'parameterCount' => new IntegerDatum(1),
                'returnType' => new StringDatum('void'),
            ],
            NodeProperties::signature($declared),
        );
    }

    public function testSignatureOffersNoResultTypeWhenNoResultIsPromised(): void
    {
        $declared = new SymbolDeclaration(signature: new Signature([new Parameter('amount', 'int')]));

        self::assertEquals(
            [
                'signature' => new StringDatum('(int $amount)'),
                'parameters' => new ListDatum([new StringDatum('amount')]),
                'parameterTypes' => new ListDatum([new StringDatum('int')]),
                'parameterCount' => new IntegerDatum(1),
            ],
            NodeProperties::signature($declared),
        );
    }

    public function testSignatureOffersNothingAtAllForASymbolThatIsNotCallable(): void
    {
        self::assertSame([], NodeProperties::signature(new SymbolDeclaration()));
    }
}
