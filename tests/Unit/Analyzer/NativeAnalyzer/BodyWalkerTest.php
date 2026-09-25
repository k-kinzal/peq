<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Usage\InstantiationEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\BodyWalker;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BodyWalker::class)]
#[Medium]
final class BodyWalkerTest extends TestCase
{
    /**
     * @param list<Edge> $expected The relations the body is expected to write
     */
    #[DataProvider('providerBodies')]
    public function testRelationsReadsWhatABodyWrites(string $code, array $expected): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => $code]);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $body = $index->sources()[0]->methodBody('App\Invoice', 'total') ?? [];
        $scope = AnalysisScope::inFile($index, 'vfs://project/Walked.php')->enteringClass('App\Invoice', null)->enteringMethod('total');

        self::assertEquals($expected, (new BodyWalker())->relations($body, $scope));
    }

    /**
     * @return iterable<string, array{string, list<Edge>}>
     */
    public static function providerBodies(): iterable
    {
        yield 'what the body itself reaches out to' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$money = new \\App\\Money(); } }\n",
            [new InstantiationEdge(new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null), new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('vfs://project/Walked.php', 3, 1, 78, 93))],
        ];

        yield 'what a closure in the body reaches out to' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$closure = function () { return new \\App\\Money(); }; } }\n",
            [new InstantiationEdge(new MethodNode(MethodNodeId::of('App\Invoice', 'total'), true, null), new ClassNode(ClassNodeId::of('App\Money'), false, null), new FileMeta('vfs://project/Walked.php', 3, 1, 101, 116))],
        ];

        yield 'an anonymous class body belongs to its own method' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$made = new class { public function inner(): mixed { return new \\App\\Money(); } }; } }\n",
            [],
        ];

        yield 'a body that reaches nothing' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): int { return 1 + 1; } }\n",
            [],
        ];
    }

    public function testRelationsSkipsUnlocatedNodesThatCannotDeclareADependency(): void
    {
        $root = vfsStream::setup('project', null, ['Walked.php' => '<?php class C { function run() {} }']);
        $index = SourceIndex::of([$root->url().'/Walked.php'], $root->url());
        $scope = AnalysisScope::inFile($index, 'vfs://project/Walked.php')->enteringClass('C', null)->enteringMethod('run');

        self::assertSame([], (new BodyWalker())->relations([new \PhpParser\Node\Stmt\Nop()], $scope));
    }
}
