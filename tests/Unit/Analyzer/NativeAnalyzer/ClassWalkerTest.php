<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\ClassWalker;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(ClassWalker::class)]
#[Medium]
final class ClassWalkerTest extends TestCase
{
    public function testWalkReadsTheMethodsAClassDeclares(): void
    {
        self::assertSame(NodeKind::Method, ParsedSnippet::walked("<?php\nnamespace App;\nclass Invoice { public function total(): void {} }\n")->nodeNamed('App\Invoice::total')?->kind());
    }

    public function testMemberReadsTheStateAClassDeclares(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\nclass Invoice { public int \$amount = 0; public const KIND = 'invoice'; }\n");

        self::assertSame(
            [NodeKind::Property, NodeKind::Constant],
            [$graph->nodeNamed('App\Invoice::amount')?->kind(), $graph->nodeNamed('App\Invoice::KIND')?->kind()],
        );
    }

    public function testMemberReadsTheCasesAnEnumDeclares(): void
    {
        self::assertSame(NodeKind::EnumCase, ParsedSnippet::walked("<?php\nnamespace App;\nenum Status { case Open; }\n")->nodeNamed('App\Status::Open')?->kind());
    }

    public function testMethodReadsWhatAMethodBodyReachesOutTo(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\nclass Money {}\nclass Invoice { public function total(): void { \$money = new Money(); } }\n");

        self::assertNotNull($graph->edge(MethodNodeId::of('App\Invoice', 'total'), \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\Money')));
    }

    public function testMethodReadsThePropertyAPromotedParameterDeclares(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\nclass Money {}\nclass Invoice { public function __construct(private readonly Money \$money) {} }\n");

        self::assertNotNull($graph->edge(PropertyNodeId::of('App\Invoice', 'money'), \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\Money')));
    }

    public function testTraitUseReadsWhatAClassTakesOnFromATrait(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Invoice { use Shared; }\n");

        self::assertNotNull($graph->nodeNamed('App\Invoice::shared'));
    }

    public function testTraitUseLeavesTheTraitsCopyOfAMethodTheClassWritesItself(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\ntrait Shared { public function shared(): int { return 1; } }\nclass Invoice { use Shared; public function shared(): int { return 2; } }\n");

        self::assertSame(4, $graph->nodeNamed('App\Invoice::shared')?->meta()?->line);
    }

    public function testTraitUseTakesAMethodOnUnderTheNameItIsRenamedTo(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Invoice { use Shared { shared as renamed; } }\n");

        self::assertSame(
            [null, NodeKind::Method],
            [$graph->nodeNamed('App\Invoice::shared')?->kind(), $graph->nodeNamed('App\Invoice::renamed')?->kind()],
        );
    }

    public function testInReadingOrderPutsAPropertyAfterTheMethodsThatMayHaveDeclaredIt(): void
    {
        $statements = ParsedSnippet::classLike("<?php\nclass Ordered { public int \$held = 0; public function instance(): void {} public static function stat(): void {} public function __construct() {} }\n")->stmts;

        self::assertSame(
            ['stat', '__construct', 'instance', 'property'],
            array_map(static fn (Stmt $statement): string => $statement instanceof ClassMethod ? $statement->name->toString() : 'property', ClassWalker::inReadingOrder($statements)),
        );
    }

    public function testRenamedLeavesAMethodAloneWhenNothingRenamesIt(): void
    {
        $method = ParsedSnippet::method("<?php\nclass Named { public function written(): void {} }\n");

        self::assertSame($method, ClassWalker::renamed($method, ['other' => 'renamed']));
    }

    public function testRenamedLeavesAStatementThatIsNotAMethodAlone(): void
    {
        $statement = ParsedSnippet::classLike("<?php\nclass Named { public int \$held = 0; }\n")->stmts[0];

        self::assertSame($statement, ClassWalker::renamed($statement, ['held' => 'renamed']));
    }

    public function testRenamedKeepsWhereAMethodIsWritten(): void
    {
        $method = ParsedSnippet::method("<?php\nclass Named { public function written(): void {} }\n");
        $renamed = ClassWalker::renamed($method, ['written' => 'taken']);

        self::assertSame(['taken', $method->getStartLine()], [$renamed instanceof ClassMethod ? $renamed->name->toString() : '', $renamed->getStartLine()]);
    }
}
