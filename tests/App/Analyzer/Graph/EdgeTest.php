<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\Graph;

use App\Analyzer\Graph\Edge\ConstFetchEdge;
use App\Analyzer\Graph\Edge\DeclarationConstantEdge;
use App\Analyzer\Graph\Edge\DeclarationEnumCaseEdge;
use App\Analyzer\Graph\Edge\DeclarationExtendsEdge;
use App\Analyzer\Graph\Edge\DeclarationImplementsEdge;
use App\Analyzer\Graph\Edge\DeclarationMethodEdge;
use App\Analyzer\Graph\Edge\DeclarationPropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTraitUseEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeParameterEdge;
use App\Analyzer\Graph\Edge\DeclarationTypePropertyEdge;
use App\Analyzer\Graph\Edge\DeclarationTypeReturnEdge;
use App\Analyzer\Graph\Edge\DeclaredInEdge;
use App\Analyzer\Graph\Edge\FunctionCallEdge;
use App\Analyzer\Graph\Edge\InstantiationEdge;
use App\Analyzer\Graph\Edge\MethodCallEdge;
use App\Analyzer\Graph\Edge\PropertyAccessEdge;
use App\Analyzer\Graph\Edge\StaticCallEdge;
use App\Analyzer\Graph\Edge\StaticPropertyAccessEdge;
use App\Analyzer\Graph\Edge\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\ConstantNode;
use App\Analyzer\Graph\Node\EnumCaseNode;
use App\Analyzer\Graph\Node\EnumNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\GraphInterfaceNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\ConstantNodeId;
use App\Analyzer\Graph\NodeId\EnumCaseNodeId;
use App\Analyzer\Graph\NodeId\EnumNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\InterfaceNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class EdgeTest extends TestCase
{
    #[Test]
    public function testFunctionCallEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'Caller', 'method'));
        $to = new FunctionNode(new FunctionNodeId('App', 'someFunction'));

        $edge = new FunctionCallEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::FunctionCall, $edge->kind());
    }

    #[Test]
    public function testMethodCallEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'Caller', 'method'));
        $to = new MethodNode(new MethodNodeId('App', 'Target', 'targetMethod'));

        $edge = new MethodCallEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::MethodCall, $edge->kind());
    }

    #[Test]
    public function testStaticCallEdge(): void
    {
        $from = new FunctionNode(new FunctionNodeId('App', 'caller'));
        $to = new MethodNode(new MethodNodeId('App', 'Target', 'staticMethod'));

        $edge = new StaticCallEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::StaticCall, $edge->kind());
    }

    #[Test]
    public function testInstantiationEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'Factory', 'create'));
        $to = new ClassNode(new ClassNodeId('App', 'Product'));

        $edge = new InstantiationEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::Instantiation, $edge->kind());
    }

    #[Test]
    public function testPropertyAccessEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'Service', 'get'));
        $to = new PropertyNode(new PropertyNodeId('App', 'Entity', 'name'));

        $edge = new PropertyAccessEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::PropertyAccess, $edge->kind());
    }

    #[Test]
    public function testStaticPropertyAccessEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'Service', 'get'));
        $to = new PropertyNode(new PropertyNodeId('App', 'Config', 'setting'));

        $edge = new StaticPropertyAccessEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::StaticPropertyAccess, $edge->kind());
    }

    #[Test]
    public function testConstFetchEdge(): void
    {
        $from = new FunctionNode(new FunctionNodeId('App', 'helper'));
        $to = new ConstantNode(new ConstantNodeId('App', 'Config', 'VERSION'));

        $edge = new ConstFetchEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::ConstFetch, $edge->kind());
    }

    #[Test]
    public function testDeclarationTraitUseEdge(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $to = new TraitNode(new TraitNodeId('App', 'MyTrait'));

        $edge = new DeclarationTraitUseEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationTraitUse, $edge->kind());
    }

    #[Test]
    public function testDeclarationExtendsEdge(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'ChildClass'));
        $to = new ClassNode(new ClassNodeId('App', 'ParentClass'));

        $edge = new DeclarationExtendsEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationExtends, $edge->kind());
    }

    #[Test]
    public function testDeclarationExtendsEdgeForInterfaces(): void
    {
        $from = new GraphInterfaceNode(new InterfaceNodeId('App', 'ChildInterface'));
        $to = new GraphInterfaceNode(new InterfaceNodeId('App', 'ParentInterface'));

        $edge = new DeclarationExtendsEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationExtends, $edge->kind());
    }

    #[Test]
    public function testDeclarationImplementsEdge(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $to = new GraphInterfaceNode(new InterfaceNodeId('App', 'MyInterface'));

        $edge = new DeclarationImplementsEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationImplements, $edge->kind());
    }

    #[Test]
    public function testDeclarationMethodEdge(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $to = new MethodNode(new MethodNodeId('App', 'MyClass', 'myMethod'));

        $edge = new DeclarationMethodEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationMethod, $edge->kind());
    }

    #[Test]
    public function testDeclarationPropertyEdge(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $to = new PropertyNode(new PropertyNodeId('App', 'MyClass', 'myProperty'));

        $edge = new DeclarationPropertyEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationProperty, $edge->kind());
    }

    #[Test]
    public function testDeclarationConstantEdge(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $to = new ConstantNode(new ConstantNodeId('App', 'MyClass', 'MY_CONSTANT'));

        $edge = new DeclarationConstantEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationConstant, $edge->kind());
    }

    #[Test]
    public function testDeclarationTypeParameterEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'MyClass', 'myMethod'));
        $to = new ClassNode(new ClassNodeId('App', 'ParamType'));

        $edge = new DeclarationTypeParameterEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationTypeParameter, $edge->kind());
    }

    #[Test]
    public function testDeclarationTypeReturnEdge(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'MyClass', 'myMethod'));
        $to = new ClassNode(new ClassNodeId('App', 'ReturnType'));

        $edge = new DeclarationTypeReturnEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationTypeReturn, $edge->kind());
    }

    #[Test]
    public function testDeclarationTypePropertyEdge(): void
    {
        $from = new PropertyNode(new PropertyNodeId('App', 'MyClass', 'myProperty'));
        $to = new ClassNode(new ClassNodeId('App', 'PropertyType'));

        $edge = new DeclarationTypePropertyEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationTypeProperty, $edge->kind());
    }

    #[Test]
    public function testInvert(): void
    {
        $fromId = new MethodNodeId('App', 'Caller', 'method');
        $toId = new MethodNodeId('App', 'Target', 'targetMethod');
        $from = new MethodNode($fromId);
        $to = new MethodNode($toId);
        $fileMeta = $this->createFileMeta();
        $edge = new MethodCallEdge($from, $to, $fileMeta);

        $inverted = $edge->invert();

        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
        self::assertSame(EdgeKind::UsedBy, $inverted->kind());
        self::assertSame($fileMeta, $inverted->meta());
    }

    #[Test]
    public function testInvertForDeclarationEdge(): void
    {
        $fromId = new ClassNodeId('App', 'ChildClass');
        $toId = new ClassNodeId('App', 'ParentClass');
        $from = new ClassNode($fromId);
        $to = new ClassNode($toId);
        $fileMeta = $this->createFileMeta();
        $edge = new DeclarationExtendsEdge($from, $to, $fileMeta);

        $inverted = $edge->invert();

        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
        self::assertSame(EdgeKind::DeclaredIn, $inverted->kind());
        self::assertSame($fileMeta, $inverted->meta());
    }

    #[Test]
    public function testDeclarationEnumCaseEdge(): void
    {
        $from = new EnumNode(new EnumNodeId('App', 'MyEnum'));
        $to = new EnumCaseNode(new EnumCaseNodeId('App', 'MyEnum', 'CaseA'));

        $edge = new DeclarationEnumCaseEdge($from, $to, $this->createFileMeta());

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame(EdgeKind::DeclarationEnumCase, $edge->kind());
    }

    #[Test]
    public function testUsedByEdgeInvertsToFunctionCall(): void
    {
        $from = new FunctionNode(new FunctionNodeId('App', 'caller'));
        $to = new FunctionNode(new FunctionNodeId('App', 'callee'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(FunctionCallEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testUsedByEdgeInvertsToConstFetch(): void
    {
        $from = new ConstantNode(new ConstantNodeId('App', 'Config', 'VERSION'));
        $to = new FunctionNode(new FunctionNodeId('App', 'user'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(ConstFetchEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testUsedByEdgeInvertsToInstantiation(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $to = new MethodNode(new MethodNodeId('App', 'Factory', 'create'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(InstantiationEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testUsedByEdgeInvertsToPropertyAccess(): void
    {
        $from = new PropertyNode(new PropertyNodeId('App', 'Entity', 'prop'));
        $to = new MethodNode(new MethodNodeId('App', 'Service', 'method'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(PropertyAccessEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testUsedByEdgeInvertsToMethodCall(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'Target', 'method'));
        $to = new MethodNode(new MethodNodeId('App', 'Caller', 'method'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(MethodCallEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationMethod(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'MyClass', 'method'));
        $to = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationMethodEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationProperty(): void
    {
        $from = new PropertyNode(new PropertyNodeId('App', 'MyClass', 'prop'));
        $to = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationPropertyEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationConstant(): void
    {
        $from = new ConstantNode(new ConstantNodeId('App', 'MyClass', 'CONST'));
        $to = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationConstantEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationEnumCase(): void
    {
        $from = new EnumCaseNode(new EnumCaseNodeId('App', 'MyEnum', 'Case'));
        $to = new EnumNode(new EnumNodeId('App', 'MyEnum'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationEnumCaseEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationExtends(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'Parent'));
        $to = new ClassNode(new ClassNodeId('App', 'Child'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationExtendsEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationImplements(): void
    {
        $from = new GraphInterfaceNode(new InterfaceNodeId('App', 'Interface'));
        $to = new ClassNode(new ClassNodeId('App', 'Class'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationImplementsEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationTraitUse(): void
    {
        $from = new TraitNode(new TraitNodeId('App', 'Trait'));
        $to = new ClassNode(new ClassNodeId('App', 'Class'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationTraitUseEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationTypeProperty(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'Type'));
        $to = new PropertyNode(new PropertyNodeId('App', 'Class', 'prop'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationTypePropertyEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testDeclaredInEdgeInvertsToDeclarationTypeReturn(): void
    {
        $from = new ClassNode(new ClassNodeId('App', 'Type'));
        $to = new MethodNode(new MethodNodeId('App', 'Class', 'method'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $inverted = $edge->invert();

        self::assertInstanceOf(DeclarationTypeReturnEdge::class, $inverted);
        self::assertSame($to->id, $inverted->from());
        self::assertSame($from->id, $inverted->to());
    }

    #[Test]
    public function testKindReturnsUsedBy(): void
    {
        $from = new FunctionNode(new FunctionNodeId('App', 'caller'));
        $to = new FunctionNode(new FunctionNodeId('App', 'callee'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        self::assertSame(EdgeKind::UsedBy, $edge->kind());
    }

    #[Test]
    public function testKindReturnsDeclaredIn(): void
    {
        $from = new MethodNode(new MethodNodeId('App', 'MyClass', 'method'));
        $to = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        self::assertSame(EdgeKind::DeclaredIn, $edge->kind());
    }

    #[Test]
    public function testEdgeTraitMethods(): void
    {
        $from = new FunctionNode(new FunctionNodeId('App', 'caller'));
        $to = new FunctionNode(new FunctionNodeId('App', 'callee'));
        $meta = $this->createFileMeta();
        $edge = new UsedByEdge($from, $to, $meta);

        self::assertSame($from, $edge->fromNode);
        self::assertSame($to, $edge->toNode);
        self::assertSame($meta, $edge->meta);

        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame($from->id, $edge->from());
        self::assertSame($to->id, $edge->to());
        self::assertSame($meta, $edge->meta());
    }

    #[Test]
    public function testUsedByEdgeInvertThrowsExceptionForUnknownNode(): void
    {
        $from = new StubNode(new ClassNodeId('App', 'Stub'));
        $to = new FunctionNode(new FunctionNodeId('App', 'callee'));
        $edge = new UsedByEdge($from, $to, $this->createFileMeta());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot invert UsedByEdge: unknown node combination.');

        $edge->invert();
    }

    #[Test]
    public function testDeclaredInEdgeInvertThrowsExceptionForUnknownNode(): void
    {
        $from = new StubNode(new ClassNodeId('App', 'Stub'));
        $to = new ClassNode(new ClassNodeId('App', 'MyClass'));
        $edge = new DeclaredInEdge($from, $to, $this->createFileMeta());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot invert DeclaredInEdge: unknown node combination.');

        $edge->invert();
    }

    private function createFileMeta(): FileMeta
    {
        return new FileMeta('/path/to/file.php', 10, 5);
    }
}
