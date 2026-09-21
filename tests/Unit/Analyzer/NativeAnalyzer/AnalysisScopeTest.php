<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use App\Analyzer\Graph\QualifiedName;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use org\bovigo\vfs\vfsStream;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AnalysisScope::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(AutoloadIndex::class)]
#[UsesClass(ClassLikeDeclaration::class)]
#[UsesClass(ParsedSource::class)]
#[UsesClass(SourceIndex::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(FunctionNodeId::class)]
#[UsesClass(MethodNodeId::class)]
#[UsesClass(UnknownNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(FunctionNode::class)]
#[UsesClass(MethodNode::class)]
#[UsesClass(UnknownNode::class)]
#[UsesClass(QualifiedName::class)]
#[Small]
final class AnalysisScopeTest extends TestCase
{
    public function testSourceNodeIsNobodyAtTheTopOfAFile(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php');

        self::assertEquals(new UnknownNode(new UnknownNodeId('/project/Only.php')), $scope->sourceNode());
    }

    public function testEnteringClassMakesTheClassOwnWhatIsWritten(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertEquals(new ClassNode(ClassNodeId::of('App\Invoice'), true, null), $scope->sourceNode());
    }

    public function testEnteringMethodMakesTheMethodOwnWhatIsWritten(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringMethod('total')
        ;

        self::assertEquals(new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null), $scope->sourceNode());
    }

    public function testEnteringFunctionMakesTheFunctionOwnWhatIsWritten(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringFunction('App\helper');

        self::assertEquals(new FunctionNode(FunctionNodeId::of('App\helper'), true, null), $scope->sourceNode());
    }

    public function testEnteringFunctionDoesNotLeaveTheClassItIsWrittenIn(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringMethod('total')
            ->enteringFunction('App\helper')
        ;

        self::assertSame('App\Invoice', $scope->className);
    }

    public function testResolveNameReadsSelfAsTheClassItIsWrittenIn(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', 'App\Record');

        self::assertSame('App\Invoice', $scope->resolveName(new Name('self')));
    }

    public function testResolveNameReadsStaticAsTheClassItIsWrittenIn(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', 'App\Record');

        self::assertSame('App\Invoice', $scope->resolveName(new Name('STATIC')));
    }

    public function testResolveNameReadsParentAsTheClassItIsWrittenUnder(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', 'App\Record');

        self::assertSame('App\Record', $scope->resolveName(new Name('parent')));
    }

    public function testResolveNameLeavesParentAloneWhereThereIsNoneToFind(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertSame('parent', $scope->resolveName(new Name('parent')));
    }

    public function testResolveNameLeavesAKeywordAloneOutsideAnyClass(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php');

        self::assertSame('self', $scope->resolveName(new Name('self')));
    }

    public function testResolveNameLeavesAWrittenNameAlone(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertSame('App\Money', $scope->resolveName(new Name('App\Money')));
    }

    public function testResolveFunctionNameFindsTheFunctionOfItsOwnNamespace(): void
    {
        $root = vfsStream::setup('project', null, ['Only.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"]);
        $index = SourceIndex::of([$root->url().'/Only.php'], $root->url());
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nhelper();\n") ?? []);
        $call = (new NodeFinder())->findFirstInstanceOf($parsed, FuncCall::class);
        self::assertInstanceOf(Name::class, $call?->name);

        self::assertSame('App\helper', AnalysisScope::inFile($index, 'vfs://project/Only.php')->resolveFunctionName($call->name));
    }

    public function testResolveFunctionNameFallsBackToTheGlobalFunction(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nhelper();\n") ?? []);
        $call = (new NodeFinder())->findFirstInstanceOf($parsed, FuncCall::class);
        self::assertInstanceOf(Name::class, $call?->name);

        self::assertSame('helper', AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->resolveFunctionName($call->name));
    }

    public function testIsReadingRemembersATraitBeingRead(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Shared')
        ;

        self::assertTrue($scope->isReading('app\shared'));
    }

    public function testIsReadingDoesNotRememberATraitThatIsNotBeingRead(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Shared')
        ;

        self::assertFalse($scope->isReading('App\Other'));
    }

    public function testReadingTraitNamesTheInnermostTraitBeingRead(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Outer')
            ->enteringTrait('App\Inner')
        ;

        self::assertSame('App\Inner', $scope->readingTrait());
    }

    public function testReadingTraitNamesNoneOutsideATrait(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertNull($scope->readingTrait());
    }

    public function testEnteringTraitKeepsTheTraitWhileItsOwnMethodIsRead(): void
    {
        $scope = AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Shared')
            ->enteringMethod('shared')
        ;

        self::assertSame('App\Shared', $scope->readingTrait());
    }

    public function testInFileStandsInTheFileItNames(): void
    {
        self::assertSame('/project/Only.php', AnalysisScope::inFile(SourceIndex::of([], '/project'), '/project/Only.php')->file);
    }
}
