<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\NodePrecedence;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\NodeDatum;
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
#[UsesClass(ExtendsEdge::class)]
#[UsesClass(MethodEdge::class)]
#[UsesClass(MethodCallEdge::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(Graph::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(NodePrecedence::class)]
#[UsesClass(QualifiedName::class)]
#[UsesClass(Edge::class)]
#[UsesClass(Node::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(Visibility::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(ListDatum::class)]
#[UsesClass(NodeDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class NodePropertiesTest extends TestCase
{
    #[DataProvider('providerPropertiesOfARoutedMethod')]
    public function testOfOffersEverythingAQueryCanAskASymbol(string $property, string $expected): void
    {
        self::assertSame($expected, NodeProperties::of(SampleGraph::show())[$property]->toText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPropertiesOfARoutedMethod(): iterable
    {
        yield 'what identifies it' => ['id', 'App\Http\Controller::show'];

        yield 'what kind of symbol it is' => ['kind', 'method'];

        yield 'what it is called' => ['name', 'show'];

        yield 'what declares it' => ['owner', 'App\Http\Controller'];

        yield 'where it is namespaced' => ['namespace', 'App\Http'];

        yield 'whether analysis found it' => ['resolved', 'TRUE'];

        yield 'where it is written' => ['file', '/project/src/Http/Controller.php'];

        yield 'which line of it' => ['line', '20'];

        yield 'how visible it is' => ['visibility', 'public'];

        yield 'the attributes written on it' => ['attributes', '[App\Http\Route]'];

        yield 'the shape its callers were written against' => ['signature', '(int $id): string'];
    }

    public function testOfLeavesOutAPropertyTheSourceSaysNothingAbout(): void
    {
        self::assertArrayNotHasKey('visibility', NodeProperties::of(SampleGraph::kernel()));
    }

    public function testOfOffersNothingDeclaredForASymbolAnalysisOnlyEverSawReferredTo(): void
    {
        $properties = NodeProperties::of(new UnknownNode(new UnknownNodeId('App\Missing')));

        self::assertSame(['id', 'kind', 'resolved', 'name', 'namespace'], array_keys($properties));
    }

    public function testAllNamesEveryPropertyASymbolCanCarry(): void
    {
        self::assertSame('STRING', NodeProperties::all()['visibility']);
    }

    public function testAllSaysWhichPropertiesHoldMoreThanOneValue(): void
    {
        self::assertSame('LIST<STRING>', NodeProperties::all()['parameters']);
    }

    #[DataProvider('providerEverySymbolOfTheSampleCodebase')]
    public function testAllCoversEveryPropertyASymbolOfTheSampleCodebaseCarries(Node $node): void
    {
        $carried = array_keys(NodeProperties::of($node));

        self::assertSame([], array_values(array_diff($carried, array_keys(NodeProperties::all()))));
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
        self::assertSame('App\Domain\Invoice', NodeProperties::naming('App\Domain\Invoice::total')['owner']->toText());
    }

    public function testNamingSplitsAClassLikeIntoItsNamespaceAndItsShortName(): void
    {
        self::assertSame('App\Domain', NodeProperties::naming('App\Domain\Invoice')['namespace']->toText());
    }

    public function testNamingGivesAClassLikeNothingToBeDeclaredBy(): void
    {
        self::assertArrayNotHasKey('owner', NodeProperties::naming('App\Domain\Invoice'));
    }

    public function testLocationReadsWhereASymbolIsWritten(): void
    {
        self::assertSame('Controller.php', NodeProperties::location(SampleGraph::controller())['fileName']->toText());
    }

    public function testLocationReadsNothingOfASymbolWrittenNowhereItKnowsOf(): void
    {
        self::assertSame([], NodeProperties::location(new UnknownNode(new UnknownNodeId('App\Missing'))));
    }

    public function testDeclaredOffersAKeywordWhetherOrNotItWasWritten(): void
    {
        self::assertSame('FALSE', NodeProperties::declared(new SymbolDeclaration())['static']->toText());
    }

    public function testDeclaredOffersTheKeywordsThatWereWritten(): void
    {
        $declared = new SymbolDeclaration(modifiers: new Modifiers(static: true), deprecated: true);

        self::assertSame('TRUE', NodeProperties::declared($declared)['deprecated']->toText());
    }

    public function testDeclaredOffersTheValueAConstantIsGiven(): void
    {
        self::assertSame('1', NodeProperties::declared(new SymbolDeclaration(value: '1'))['value']->toText());
    }

    public function testDeclaredOffersTheTypeAPropertyIsGiven(): void
    {
        self::assertSame('int', NodeProperties::declared(new SymbolDeclaration(type: 'int'))['type']->toText());
    }

    public function testSignatureOffersTheShapeACallablesCallersWereWrittenAgainst(): void
    {
        $declared = new SymbolDeclaration(signature: new Signature([new Parameter('amount', 'int')], 'void'));

        self::assertSame('(int $amount): void', NodeProperties::signature($declared)['signature']->toText());
    }

    public function testSignatureOffersTheParametersSeparatelyForAQueryToCount(): void
    {
        $declared = new SymbolDeclaration(signature: new Signature([new Parameter('amount', 'int')]));

        self::assertSame('1', NodeProperties::signature($declared)['parameterCount']->toText());
    }

    public function testSignatureOffersNothingWhenNoResultIsPromised(): void
    {
        $declared = new SymbolDeclaration(signature: new Signature([new Parameter('amount', 'int')]));

        self::assertArrayNotHasKey('returnType', NodeProperties::signature($declared));
    }

    public function testSignatureOffersNothingAtAllForASymbolThatIsNotCallable(): void
    {
        self::assertSame([], NodeProperties::signature(new SymbolDeclaration()));
    }
}
