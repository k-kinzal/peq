<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Analyzer\ReceiverBinding;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\Declaration\Modifiers::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\Parameter::class)]
#[UsesClass(\App\Analyzer\Graph\Declaration\Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(Graph::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(PropertyNodeId::class)]
#[UsesClass(FunctionNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(PropertyNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(ClassHierarchy::class)]
#[UsesClass(\App\Analyzer\Graph\Resolution\TypeConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Analyzer\Declaration\PhpDoc')]
#[CoversClass(ReceiverBinding::class)]
#[Small]
final class ReceiverBindingTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMemberExpressions')]
    public function testIsMemberRecognizesBothNullsafeAndOrdinaryMembers(string $source, bool $expected): void
    {
        $nodes = (new \PhpParser\ParserFactory())->createForHostVersion()->parse($source) ?? [];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $nodes[0]);

        self::assertSame($expected, ReceiverBinding::isMember($nodes[0]->expr));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerMemberExpressions(): iterable
    {
        yield 'method' => ['<?php $value->run();', true];

        yield 'nullsafe property' => ['<?php $value?->item;', true];

        yield 'variable' => ['<?php $value;', false];
    }

    public function testAnnotateAndDocumentedTypeApplyInlineTypesToAssignmentsAndIteration(): void
    {
        $nodes = (new \PhpParser\ParserFactory())->createForHostVersion()->parse('<?php /** @var list<Item> */ $values = $unknown; foreach ($values as $value) { $value->run(); }') ?? [];
        $index = new \App\Analyzer\Declaration\PhpDoc\DocIndex();
        $index->read(array_values($nodes));
        $binding = new ReceiverBinding(new ClassHierarchy(new Graph()), new FunctionNode(FunctionNodeId::of('run')), $index);
        $binding->annotate($nodes[0]);
        $binding->annotate($nodes[1]);

        self::assertSame('Item', $binding->documentedType(new Variable('value'), 0)?->objects());
        self::assertSame('', $binding->of(new Variable('values')));
        self::assertNull($binding->documentedType(new Variable('value'), 17));
    }

    public function testDocumentedTypeDoesNotBorrowAnUnrelatedVariable(): void
    {
        $binding = new ReceiverBinding(new ClassHierarchy(new Graph()), new FunctionNode(FunctionNodeId::of('run')));

        self::assertNull($binding->documentedType(new Variable('unknown'), 0));
        self::assertNull($binding->documentedType(new \PhpParser\Node\Scalar\String_('Item'), 0));
    }

    public function testOfTypedParameterAndNewExpressionResolveTheirReceivers(): void
    {
        $source = new FunctionNode(
            FunctionNodeId::of('invoke'),
            true,
            null,
            new SymbolDeclaration(signature: new \App\Analyzer\Graph\Declaration\Signature([
                new \App\Analyzer\Graph\Declaration\Parameter('port', 'Port&Tagged'),
            ])),
        );
        $types = new ReceiverBinding(new ClassHierarchy(new Graph()), $source);

        self::assertSame('Port&Tagged', $types->of(new Variable('port')));
        self::assertSame('Service', $types->of(new New_(new Name('Service'))));
        self::assertSame('', $types->of(new Variable('unknown')));
    }

    public function testAssignKeepsAllKnownAssignmentAlternatives(): void
    {
        $binding = new ReceiverBinding(new ClassHierarchy(new Graph()), new FunctionNode(FunctionNodeId::of('run')));
        $binding->assign(new Assign(new Variable('port'), new New_(new Name('First'))));
        $binding->assign(new Assign(new Variable('port'), new New_(new Name('Second'))));

        self::assertSame('First|Second', $binding->of(new Variable('port')));
    }

    public function testNameResolvesSelfAgainstTheLexicalClass(): void
    {
        $binding = new ReceiverBinding(new ClassHierarchy(new Graph()), new MethodNode(MethodNodeId::of('Service', 'run')));

        self::assertSame('Service', $binding->name(new Name('self')));
        self::assertSame('Service', $binding->name(new Name('static')));
    }

    public function testMemberTypeReadsADeclaredProperty(): void
    {
        $graph = new Graph();
        $graph->addNode(new PropertyNode(PropertyNodeId::of('Controller', 'port'), true, null, new SymbolDeclaration(type: 'Port')));
        $binding = new ReceiverBinding(new ClassHierarchy($graph), new MethodNode(MethodNodeId::of('Controller', 'run')));

        self::assertSame('Port', $binding->memberType(new PropertyFetch(new Variable('this'), 'port'), false, 0));
    }

    public function testMemberTypePreservesPropertyNameCasing(): void
    {
        $graph = new Graph();
        $graph->addNode(new PropertyNode(PropertyNodeId::of('Controller', 'port'), true, null, new SymbolDeclaration(type: 'ReadPort')));
        $graph->addNode(new PropertyNode(PropertyNodeId::of('Controller', 'Port'), true, null, new SymbolDeclaration(type: 'WritePort')));
        $binding = new ReceiverBinding(new ClassHierarchy($graph), new MethodNode(MethodNodeId::of('Controller', 'run')));

        self::assertSame('ReadPort', $binding->of(new PropertyFetch(new Variable('this'), 'port')));
        self::assertSame('WritePort', $binding->of(new PropertyFetch(new Variable('this'), 'Port')));
    }

    public function testOfFollowsNullablePropertiesMethodsAndFunctionReturnTypes(): void
    {
        $graph = new Graph();
        $graph->addNodes([
            new PropertyNode(PropertyNodeId::of('Controller', 'port'), true, null, new SymbolDeclaration(type: 'Port')),
            new MethodNode(MethodNodeId::of('Controller', 'getPort'), true, null, new SymbolDeclaration(signature: new \App\Analyzer\Graph\Declaration\Signature(returnType: 'ReturnedPort'))),
            new FunctionNode(FunctionNodeId::of('create'), true, null, new SymbolDeclaration(signature: new \App\Analyzer\Graph\Declaration\Signature(returnType: 'CreatedPort'))),
        ]);
        $binding = new ReceiverBinding(new ClassHierarchy($graph), new MethodNode(MethodNodeId::of('Controller', 'run')));

        self::assertSame('Port', $binding->of(new \PhpParser\Node\Expr\NullsafePropertyFetch(new Variable('this'), 'port')));
        self::assertSame('ReturnedPort', $binding->of(new \PhpParser\Node\Expr\MethodCall(new Variable('this'), 'getPort')));
        self::assertSame('ReturnedPort', $binding->of(new \PhpParser\Node\Expr\NullsafeMethodCall(new Variable('this'), 'getPort')));
        self::assertSame('CreatedPort', $binding->of(new \PhpParser\Node\Expr\FuncCall(new Name('create'))));
        self::assertSame('', $binding->of(new \PhpParser\Node\Expr\FuncCall(new Name('unknown'))));
    }

    public function testOfKeepsBothConditionalReceiverAlternatives(): void
    {
        $binding = new ReceiverBinding(new ClassHierarchy(new Graph()), new FunctionNode(FunctionNodeId::of('run')));
        $first = new New_(new Name('First'));
        $second = new New_(new Name('Second'));

        self::assertSame('First|Second', $binding->of(new \PhpParser\Node\Expr\Ternary(new Variable('condition'), $first, $second)));
        self::assertSame('First|Second', $binding->of(new \PhpParser\Node\Expr\Ternary($first, null, $second)));
        self::assertSame('First|Second', $binding->of(new \PhpParser\Node\Expr\BinaryOp\Coalesce($first, $second)));
    }

    public function testOfBoundsRecursiveMemberResolution(): void
    {
        $graph = new Graph();
        $graph->addNode(new PropertyNode(PropertyNodeId::of('Service', 'next'), true, null, new SymbolDeclaration(type: 'self')));
        $binding = new ReceiverBinding(new ClassHierarchy($graph), new MethodNode(MethodNodeId::of('Service', 'run')));
        $statements = (new \PhpParser\ParserFactory())->createForHostVersion()->parse('<?php $this'.str_repeat('->next', 16).';');
        self::assertNotNull($statements);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $statements[0]);
        $chain = $statements[0]->expr;

        self::assertSame('Service', $binding->of($chain));
        self::assertSame('', $binding->of(new PropertyFetch($chain, 'next')));
    }

    public function testMemberTypeKeepsUnknownMembersUnknownAndDeduplicatesReturnTypes(): void
    {
        $graph = new Graph();
        $graph->addNodes([
            new MethodNode(MethodNodeId::of('First', 'run'), true, null, new SymbolDeclaration(signature: new \App\Analyzer\Graph\Declaration\Signature(returnType: 'Result'))),
            new MethodNode(MethodNodeId::of('Second', 'run'), true, null, new SymbolDeclaration(signature: new \App\Analyzer\Graph\Declaration\Signature(returnType: 'Result'))),
            new MethodNode(MethodNodeId::of('First', 'undocumented')),
            new MethodNode(MethodNodeId::of('First', 'unsigned'), true, null, new SymbolDeclaration()),
            new PropertyNode(PropertyNodeId::of('First', 'undocumentedProperty')),
        ]);
        $binding = new ReceiverBinding(new ClassHierarchy($graph), new MethodNode(MethodNodeId::of('First', 'source')));
        $both = new \PhpParser\Node\Expr\BinaryOp\Coalesce(new New_(new Name('First')), new New_(new Name('Second')));

        self::assertSame('Result', $binding->of(new \PhpParser\Node\Expr\MethodCall($both, 'run')));
        self::assertSame('', $binding->of(new \PhpParser\Node\Expr\MethodCall(new Variable('this'), 'unknown')));
        self::assertSame('', $binding->of(new \PhpParser\Node\Expr\MethodCall(new Variable('this'), 'undocumented')));
        self::assertSame('', $binding->of(new \PhpParser\Node\Expr\MethodCall(new Variable('this'), 'unsigned')));
        self::assertSame('', $binding->of(new PropertyFetch(new Variable('this'), 'unknown')));
        self::assertSame('', $binding->of(new PropertyFetch(new Variable('this'), 'undocumentedProperty')));
        self::assertSame('', $binding->of(new PropertyFetch(new Variable('this'), new Variable('name'))));
    }

    public function testAssignIgnoresUnknownValuesAndRepeatedAlternatives(): void
    {
        $binding = new ReceiverBinding(new ClassHierarchy(new Graph()), new FunctionNode(FunctionNodeId::of('run')));
        $binding->assign(new Assign(new Variable('port'), new New_(new Name('Port'))));
        $binding->assign(new Assign(new Variable('port'), new New_(new Name('Port'))));
        $binding->assign(new Assign(new Variable('port'), new Variable('unknown')));
        $binding->assign(new Assign(new Variable(new Variable('name')), new New_(new Name('Other'))));
        $binding->assign(new Assign(new PropertyFetch(new Variable('object'), 'port'), new New_(new Name('Other'))));

        self::assertSame('Port', $binding->of(new Variable('port')));
        self::assertSame('', $binding->of(new Variable(new Variable('name'))));
    }
}
