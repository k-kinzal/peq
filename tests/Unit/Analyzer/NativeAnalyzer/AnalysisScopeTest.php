<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(AnalysisScope::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(AutoloadIndex::class)]
#[UsesClass(ClassLikeDeclaration::class)]
#[UsesClass(ParsedSource::class)]
#[UsesClass(SourceIndex::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\FunctionNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\UnknownNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\FunctionNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\UnknownNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class AnalysisScopeTest extends TestCase
{
    public function testSourceNodeIsNobodyAtTheTopOfAFile(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php');

        self::assertSame(NodeKind::Unknown, $scope->sourceNode()->kind());
    }

    public function testEnteringClassMakesTheClassOwnWhatIsWritten(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertSame('App\Invoice', $scope->sourceNode()->id()->toString());
    }

    public function testEnteringMethodMakesTheMethodOwnWhatIsWritten(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringMethod('total')
        ;

        self::assertSame('App\Invoice::total', $scope->sourceNode()->id()->toString());
    }

    public function testEnteringFunctionMakesTheFunctionOwnWhatIsWritten(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringFunction('App\helper');

        self::assertSame('App\helper', $scope->sourceNode()->id()->toString());
    }

    public function testEnteringFunctionDoesNotLeaveTheClassItIsWrittenIn(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringMethod('total')
            ->enteringFunction('App\helper')
        ;

        self::assertSame('App\Invoice', $scope->className);
    }

    public function testResolveNameReadsSelfAsTheClassItIsWrittenIn(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', 'App\Record');

        self::assertSame('App\Invoice', $scope->resolveName(new Name('self')));
    }

    public function testResolveNameReadsStaticAsTheClassItIsWrittenIn(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', 'App\Record');

        self::assertSame('App\Invoice', $scope->resolveName(new Name('STATIC')));
    }

    public function testResolveNameReadsParentAsTheClassItIsWrittenUnder(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', 'App\Record');

        self::assertSame('App\Record', $scope->resolveName(new Name('parent')));
    }

    public function testResolveNameLeavesParentAloneWhereThereIsNoneToFind(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertSame('parent', $scope->resolveName(new Name('parent')));
    }

    public function testResolveNameLeavesAKeywordAloneOutsideAnyClass(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php');

        self::assertSame('self', $scope->resolveName(new Name('self')));
    }

    public function testResolveNameLeavesAWrittenNameAlone(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertSame('App\Money', $scope->resolveName(new Name('App\Money')));
    }

    public function testResolveFunctionNameFindsTheFunctionOfItsOwnNamespace(): void
    {
        $index = ParsedSnippet::index(['Only.php' => "<?php\nnamespace App;\nfunction helper(): void {}\n"]);
        $call = (new NodeFinder())->findFirstInstanceOf(ParsedSnippet::statements("<?php\nnamespace App;\nhelper();\n"), FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        self::assertInstanceOf(Name::class, $call->name);

        self::assertSame('App\helper', AnalysisScope::inFile($index, '/project/Only.php')->resolveFunctionName($call->name));
    }

    public function testResolveFunctionNameFallsBackToTheGlobalFunction(): void
    {
        $index = ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]);
        $call = (new NodeFinder())->findFirstInstanceOf(ParsedSnippet::statements("<?php\nnamespace App;\nhelper();\n"), FuncCall::class);
        self::assertInstanceOf(FuncCall::class, $call);
        self::assertInstanceOf(Name::class, $call->name);

        self::assertSame('helper', AnalysisScope::inFile($index, '/project/Only.php')->resolveFunctionName($call->name));
    }

    public function testIsReadingRemembersATraitBeingRead(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Shared')
        ;

        self::assertTrue($scope->isReading('app\shared'));
    }

    public function testIsReadingDoesNotRememberATraitThatIsNotBeingRead(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Shared')
        ;

        self::assertFalse($scope->isReading('App\Other'));
    }

    public function testReadingTraitNamesTheInnermostTraitBeingRead(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Outer')
            ->enteringTrait('App\Inner')
        ;

        self::assertSame('App\Inner', $scope->readingTrait());
    }

    public function testReadingTraitNamesNoneOutsideATrait(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->enteringClass('App\Invoice', null);

        self::assertNull($scope->readingTrait());
    }

    public function testEnteringTraitKeepsTheTraitWhileItsOwnMethodIsRead(): void
    {
        $scope = AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')
            ->enteringClass('App\Invoice', null)
            ->enteringTrait('App\Shared')
            ->enteringMethod('shared')
        ;

        self::assertSame('App\Shared', $scope->readingTrait());
    }

    public function testInFileStandsInTheFileItNames(): void
    {
        self::assertSame('/project/Only.php', AnalysisScope::inFile(ParsedSnippet::index(['Only.php' => "<?php\nclass One {}\n"]), '/project/Only.php')->file);
    }
}
