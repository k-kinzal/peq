<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocOwners;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node\Const_;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocOwners::class)]
#[UsesNamespace('App\Analyzer')]
#[Small]
final class DocOwnersTest extends TestCase
{
    public function testOfOnePropertyStatementFindsEveryDeclaredProperty(): void
    {
        $graph = new Graph();
        $first = new PropertyNode(PropertyNodeId::of('Subject', 'first'), true);
        $second = new PropertyNode(PropertyNodeId::of('Subject', 'second'), true);
        $graph->addNodes([$first, $second]);
        $scope = new DocScope(new NameContext(new Collecting()), 'Subject');
        $statement = new Property(0, [new PropertyItem('first'), new PropertyItem('second')]);

        self::assertSame([$first, $second], DocOwners::of($statement, $scope, $graph));
        self::assertNull(DocOwners::of(new Nop(), $scope, $graph));
        self::assertNull(DocOwners::of(new ClassMethod('unusedTraitMethod'), $scope, $graph));
    }

    public function testOfClassUsesTheNamespacedScope(): void
    {
        $graph = new Graph();
        $symbol = new ClassNode(ClassNodeId::of('App\Subject'), true);
        $graph->addNode($symbol);
        $scope = new DocScope(new NameContext(new Collecting()), 'App\Subject');

        self::assertSame([$symbol], DocOwners::of(new Class_('Subject'), $scope, $graph));
        self::assertSame([], DocOwners::of(new Class_(null), new DocScope($scope->names), $graph));
    }

    public function testOfFunctionUsesItsResolvedName(): void
    {
        $graph = new Graph();
        $symbol = new FunctionNode(FunctionNodeId::of('App\run'), true);
        $graph->addNode($symbol);
        $scope = new DocScope(new NameContext(new Collecting()));
        $statement = new Function_('run');
        $statement->namespacedName = new Name('App\run');
        $unresolved = new Function_('unresolved');
        $unresolved->namespacedName = null;

        self::assertSame([$symbol], DocOwners::of($statement, $scope, $graph));
        self::assertSame([], DocOwners::of($unresolved, $scope, $graph));
    }

    public function testOfMethodFindsTheMemberOfTheEnclosingClass(): void
    {
        $graph = new Graph();
        $symbol = new MethodNode(MethodNodeId::of('App\Subject', 'run'), true);
        $graph->addNode($symbol);
        $scope = new DocScope(new NameContext(new Collecting()), 'App\Subject');

        self::assertSame([$symbol], DocOwners::of(new ClassMethod('run'), $scope, $graph));
    }

    public function testOfOneConstantStatementFindsEveryDeclaredConstant(): void
    {
        $graph = new Graph();
        $first = new ConstantNode(ConstantNodeId::of('App\Subject', 'FIRST'), true);
        $second = new ConstantNode(ConstantNodeId::of('App\Subject', 'SECOND'), true);
        $graph->addNodes([$first, $second]);
        $scope = new DocScope(new NameContext(new Collecting()), 'App\Subject');
        $statement = new ClassConst([new Const_('FIRST', new Int_(1)), new Const_('SECOND', new Int_(2))]);

        self::assertSame([$first, $second], DocOwners::of($statement, $scope, $graph));
    }

    public function testOfEnumCaseFindsTheCaseOfTheEnclosingEnum(): void
    {
        $graph = new Graph();
        $symbol = new EnumCaseNode(EnumCaseNodeId::of('App\Status', 'Ready'), true);
        $graph->addNode($symbol);
        $scope = new DocScope(new NameContext(new Collecting()), 'App\Status');

        self::assertSame([$symbol], DocOwners::of(new EnumCase('Ready'), $scope, $graph));
    }

    public function testOfPropertiesOmitsMissingDeclarationsAndReturnsAList(): void
    {
        $graph = new Graph();
        $symbol = new PropertyNode(PropertyNodeId::of('Subject', 'known'), true);
        $graph->addNode($symbol);
        $scope = new DocScope(new NameContext(new Collecting()), 'Subject');
        $statement = new Property(0, [new PropertyItem('missing'), new PropertyItem('known')]);

        self::assertSame([$symbol], DocOwners::of($statement, $scope, $graph));
        self::assertSame([], DocOwners::of(new Property(0, [new PropertyItem('missing')]), $scope, $graph));
    }
}
