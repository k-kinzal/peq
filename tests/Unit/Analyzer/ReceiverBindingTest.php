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
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReceiverBinding::class)]
#[Small]
final class ReceiverBindingTest extends TestCase
{
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
}
