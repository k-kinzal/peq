<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\TraitNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\TraitNodeId;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\GraphRecorder;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use App\Analyzer\NativeAnalyzer\SourceWalker;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceWalker::class)]
#[Medium]
final class SourceWalkerTest extends TestCase
{
    public function testWalkFileReachesTheClassesAFileDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walkFile($index->sources()[0]);

        self::assertEquals(
            new ClassNode(ClassNodeId::of('App\Invoice'), true, new FileMeta('vfs://project/Walked.php', 3, 1), new SymbolDeclaration()),
            $recorder->graph()->nodeNamed('App\Invoice'),
        );
    }

    public function testWalkFileReachesTheFunctionsAFileDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walkFile($index->sources()[0]);

        self::assertEquals(
            new FunctionNode(FunctionNodeId::of('App\helper'), true, new FileMeta('vfs://project/Walked.php', 3, 1), new SymbolDeclaration(signature: new Signature([], 'void'))),
            $recorder->graph()->nodeNamed('App\helper'),
        );
    }

    public function testWalkClassLikeRecordsATraitWithoutReadingWhatItHolds(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $trait = $source->declarationOf('App\Shared');
        self::assertNotNull($trait);
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walkClassLike($trait, AnalysisScope::inFile($index, $source->path), $source);

        self::assertEquals(
            [new TraitNode(TraitNodeId::of('App\Shared'), true, new FileMeta('vfs://project/Walked.php', 3, 1), new SymbolDeclaration())],
            $recorder->graph()->nodes(),
        );
    }

    public function testDescendReachesADeclarationWrittenInsideAnotherStatement(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nif (true) { class Conditional {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->descend($source->statements[0], AnalysisScope::inFile($index, $source->path), $source);

        self::assertEquals(
            new ClassNode(ClassNodeId::of('App\Conditional'), true, new FileMeta('vfs://project/Walked.php', 3, 1), new SymbolDeclaration()),
            $recorder->graph()->nodeNamed('App\Conditional'),
        );
    }

    public function testWalkRecordsWhatAFunctionBodyReachesOutTo(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Money {}\nfunction helper(): void { \$money = new Money(); }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walk($source->statements[0], AnalysisScope::inFile($index, $source->path), $source);

        self::assertEquals(
            new InstantiationEdge(new FunctionNode(FunctionNodeId::of('App\helper'), true, null), new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('vfs://project/Walked.php', 4, 1, 71)),
            $recorder->graph()->edge(FunctionNodeId::of('App\helper'), ClassNodeId::of('App\Money')),
        );
    }

    public function testWalkRecordsWhatAClosureInAFunctionReachesOutToAsTheFunctionsOwn(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Money {}\nfunction helper(): void { \$closure = function () { return new Money(); }; }\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walk($source->statements[0], AnalysisScope::inFile($index, $source->path), $source);

        self::assertEquals(
            new InstantiationEdge(new FunctionNode(FunctionNodeId::of('App\helper'), true, null), new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('vfs://project/Walked.php', 4, 1, 94)),
            $recorder->graph()->edge(FunctionNodeId::of('App\helper'), ClassNodeId::of('App\Money')),
        );
    }

    public function testWalkRecordsNothingForWhatIsWrittenOutsideAnyDeclaration(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\nclass Money {}\n\$money = new Money();\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $source = $index->sources()[0];
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walk($source->statements[0], AnalysisScope::inFile($index, $source->path), $source);

        self::assertSame([], $recorder->graph()->forwardEdges());
    }

    public function testWalkClassLikeNamesAnAnonymousClassAfterWhereItStands(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => "<?php\nnamespace App;\n\$made = new class { public function inner(): void {} };\n"]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $recorder = new GraphRecorder();

        (new SourceWalker($index, $recorder))->walkFile($index->sources()[0]);

        self::assertSame(
            ['AnonymousClass'.md5('Walked.php:3').'::inner', 'AnonymousClass'.md5('Walked.php:3')],
            array_map(static fn (Node $node): string => $node->id()->toString(), $recorder->graph()->nodes()),
        );
    }
}
