<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\SourceWalker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(SourceWalker::class)]
#[Medium]
final class SourceWalkerTest extends TestCase
{
    public function testWalkFileReachesTheClassesAFileDeclares(): void
    {
        self::assertSame(NodeKind::Klass, ParsedSnippet::walked("<?php\nnamespace App;\nclass Invoice {}\n")->nodeNamed('App\Invoice')?->kind());
    }

    public function testWalkFileReachesTheFunctionsAFileDeclares(): void
    {
        self::assertSame(NodeKind::Function, ParsedSnippet::walked("<?php\nnamespace App;\nfunction helper(): void {}\n")->nodeNamed('App\helper')?->kind());
    }

    public function testWalkClassLikeRecordsATraitWithoutReadingWhatItHolds(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\n");

        self::assertSame([NodeKind::Trait], array_map(static fn (object $node): NodeKind => $node->kind(), $graph->nodes()));
    }

    public function testDescendReachesADeclarationWrittenInsideAnotherStatement(): void
    {
        self::assertNotNull(ParsedSnippet::walked("<?php\nnamespace App;\nif (true) { class Conditional {} }\n")->nodeNamed('App\Conditional'));
    }

    public function testWalkRecordsWhatAFunctionBodyReachesOutTo(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\nclass Money {}\nfunction helper(): void { \$money = new Money(); }\n");

        self::assertNotNull($graph->edge(
            \App\Analyzer\Graph\NodeId\FunctionNodeId::of('App\helper'),
            \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\Money'),
        ));
    }

    public function testWalkRecordsWhatAClosureInAFunctionReachesOutToAsTheFunctionsOwn(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\nclass Money {}\nfunction helper(): void { \$closure = function () { return new Money(); }; }\n");

        self::assertNotNull($graph->edge(
            \App\Analyzer\Graph\NodeId\FunctionNodeId::of('App\helper'),
            \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\Money'),
        ));
    }

    public function testWalkRecordsNothingForWhatIsWrittenOutsideAnyDeclaration(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\nclass Money {}\n\$money = new Money();\n");

        self::assertSame([], $graph->edges(\App\Analyzer\Graph\NodeId\ClassNodeId::of('App\Money')));
    }

    public function testWalkClassLikeNamesAnAnonymousClassAfterWhereItStands(): void
    {
        $graph = ParsedSnippet::walked("<?php\nnamespace App;\n\$made = new class { public function inner(): void {} };\n");
        $names = array_map(static fn (object $node): string => $node->id()->toString(), $graph->nodes());

        self::assertSame(['AnonymousClass'.md5('Walked.php:3').'::inner', 'AnonymousClass'.md5('Walked.php:3')], $names);
    }
}
