<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer;

use App\Analyzer\ExperimentAnalyzer\AnalysisScope;
use App\Analyzer\ExperimentAnalyzer\ClassWalker;
use App\Analyzer\ExperimentAnalyzer\GraphRecorder;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\ExperimentAnalyzer\SourceWalker;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;
use org\bovigo\vfs\vfsStream;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClassWalker::class)]
#[Medium]
final class ClassWalkerTest extends TestCase
{
    public function testWalkReadsTheMethodsAClassDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Invoice { public function total(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        self::assertNotNull($class);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->walk($class, NodeKind::Klass, 'App\Invoice', AnalysisScope::inFile($index, $source->path), $source);

        self::assertEquals(
            new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, new FileMeta('vfs://project/Walked.php', 3, 1), new SymbolDeclaration(Visibility::Public, new Modifiers(), new Signature([], 'void'))),
            $recorder->graph()->nodeNamed('App\Invoice::total'),
        );
    }

    public function testMemberReadsTheStateAClassDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Invoice { public int \$amount = 0; public const KIND = 'invoice'; }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        self::assertNotNull($class);
        $scope = AnalysisScope::inFile($index, $source->path)->enteringClass('App\Invoice', null);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->member($class->stmts[0], $class, NodeKind::Klass, $scope, $source);
        $walker->member($class->stmts[1], $class, NodeKind::Klass, $scope, $source);

        self::assertSame(
            [NodeKind::Property, NodeKind::Constant],
            [$recorder->graph()->nodeNamed('App\Invoice::amount')?->kind(), $recorder->graph()->nodeNamed('App\Invoice::KIND')?->kind()],
        );
    }

    public function testMemberReadsTheCasesAnEnumDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nenum Status { case Open; }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $enum = $source->declarationOf('App\Status');
        self::assertNotNull($enum);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->member($enum->stmts[0], $enum, NodeKind::Enum, AnalysisScope::inFile($index, $source->path)->enteringClass('App\Status', null), $source);

        self::assertSame(NodeKind::EnumCase, $recorder->graph()->nodeNamed('App\Status::Open')?->kind());
    }

    public function testMethodReadsWhatAMethodBodyReachesOutTo(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Money {}\nclass Invoice { public function total(): void { \$money = new Money(); } }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        $method = $class?->getMethod('total');
        self::assertNotNull($class);
        self::assertNotNull($method);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->method($method, $class, NodeKind::Klass, AnalysisScope::inFile($index, $source->path)->enteringClass('App\Invoice', null), $source);

        self::assertEquals(
            new InstantiationEdge(new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null), new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('vfs://project/Walked.php', 4, 1, 93)),
            $recorder->graph()->edge(MethodNodeId::of('App\Invoice', 'total'), ClassNodeId::of('App\Money')),
        );
    }

    public function testMethodReadsThePropertyAPromotedParameterDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Money {}\nclass Invoice { public function __construct(private readonly Money \$money) {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        $method = $class?->getMethod('__construct');
        self::assertNotNull($class);
        self::assertNotNull($method);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->method($method, $class, NodeKind::Klass, AnalysisScope::inFile($index, $source->path)->enteringClass('App\Invoice', null), $source);

        self::assertSame(EdgeKind::DeclarationTypeProperty, $recorder->graph()->edge(PropertyNodeId::of('App\Invoice', 'money'), ClassNodeId::of('App\Money'))?->kind());
    }

    public function testTraitUseReadsWhatAClassTakesOnFromATrait(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Invoice { use Shared; }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        self::assertNotNull($class);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->traitUse($class->getTraitUses()[0], $class, NodeKind::Klass, AnalysisScope::inFile($index, $source->path)->enteringClass('App\Invoice', null));

        self::assertSame(NodeKind::Method, $recorder->graph()->nodeNamed('App\Invoice::shared')?->kind());
    }

    public function testTraitUseLeavesOutTheTraitsCopyOfAMethodTheClassWritesItself(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): int { return 1; } public function kept(): int { return 3; } }\nclass Invoice { use Shared; public function shared(): int { return 2; } }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        self::assertNotNull($class);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->traitUse($class->getTraitUses()[0], $class, NodeKind::Klass, AnalysisScope::inFile($index, $source->path)->enteringClass('App\Invoice', null));

        self::assertSame(
            [null, NodeKind::Method],
            [$recorder->graph()->nodeNamed('App\Invoice::shared')?->kind(), $recorder->graph()->nodeNamed('App\Invoice::kept')?->kind()],
        );
    }

    public function testTraitUseTakesAMethodOnUnderTheNameItIsRenamedTo(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Invoice { use Shared { shared as renamed; } }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $class = $source->declarationOf('App\Invoice');
        self::assertNotNull($class);
        $recorder = new GraphRecorder();
        $walker = new ClassWalker($index, $recorder, new SourceWalker($index, $recorder));

        $walker->traitUse($class->getTraitUses()[0], $class, NodeKind::Klass, AnalysisScope::inFile($index, $source->path)->enteringClass('App\Invoice', null));

        self::assertSame(
            [null, NodeKind::Method],
            [$recorder->graph()->nodeNamed('App\Invoice::shared')?->kind(), $recorder->graph()->nodeNamed('App\Invoice::renamed')?->kind()],
        );
    }

    public function testInReadingOrderPutsAPropertyAfterTheMethodsThatMayHaveDeclaredIt(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse("<?php\nclass Ordered { public int \$held = 0; public function instance(): void {} public static function stat(): void {} public function __construct() {} }\n") ?? [];
        $class = (new NodeFinder())->findFirstInstanceOf($statements, Class_::class);
        self::assertNotNull($class);

        self::assertSame(
            ['stat', '__construct', 'instance', 'property'],
            array_map(static fn (Stmt $statement): string => $statement instanceof ClassMethod ? $statement->name->toString() : 'property', ClassWalker::inReadingOrder($class->stmts)),
        );
    }

    public function testRenamedLeavesAMethodAloneWhenNothingRenamesIt(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse("<?php\nclass Named { public function written(): void {} }\n") ?? [];
        $method = (new NodeFinder())->findFirstInstanceOf($statements, ClassMethod::class);
        self::assertNotNull($method);

        self::assertSame($method, ClassWalker::renamed($method, ['other' => 'renamed']));
    }

    public function testRenamedLeavesAStatementThatIsNotAMethodAlone(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse("<?php\nclass Named { public int \$held = 0; }\n") ?? [];
        $class = (new NodeFinder())->findFirstInstanceOf($statements, Class_::class);
        self::assertNotNull($class);

        self::assertSame($class->stmts[0], ClassWalker::renamed($class->stmts[0], ['held' => 'renamed']));
    }

    public function testRenamedKeepsWhereAMethodIsWritten(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse("<?php\nclass Named {\n    public function written(): void {}\n}\n") ?? [];
        $method = (new NodeFinder())->findFirstInstanceOf($statements, ClassMethod::class);
        self::assertNotNull($method);

        $renamed = ClassWalker::renamed($method, ['written' => 'taken']);

        self::assertInstanceOf(ClassMethod::class, $renamed);
        self::assertSame(['taken', 3], [$renamed->name->toString(), $renamed->getStartLine()]);
    }

    public function testRenamedAppliesTheVisibilityWrittenByAnAlias(): void
    {
        $statements = (new ParserFactory())->createForHostVersion()->parse('<?php trait Shared { public function written(): void {} } class Named { use Shared { written as protected taken; } }') ?? [];
        $method = (new NodeFinder())->findFirstInstanceOf($statements, ClassMethod::class);
        $class = (new NodeFinder())->findFirstInstanceOf($statements, Class_::class);
        self::assertNotNull($method);
        self::assertNotNull($class);

        $renamed = ClassWalker::renamed($method, ['written' => 'taken'], $class->getTraitUses()[0], 'Shared');

        self::assertInstanceOf(ClassMethod::class, $renamed);
        self::assertTrue($renamed->isProtected());
        self::assertTrue($method->isPublic());
    }
}
