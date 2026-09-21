<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\AutoloadIndex;
use App\Analyzer\NativeAnalyzer\ClassLikeDeclaration;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use App\Analyzer\NativeAnalyzer\TraitFlattening;
use App\Analyzer\NativeAnalyzer\TraitMethod;
use App\Analyzer\SourceParser;
use org\bovigo\vfs\vfsStream;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
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
#[CoversClass(TraitFlattening::class)]
#[UsesClass(AnonymousClassNaming::class)]
#[UsesClass(AutoloadIndex::class)]
#[UsesClass(ClassLikeDeclaration::class)]
#[UsesClass(ParsedSource::class)]
#[UsesClass(SourceIndex::class)]
#[UsesClass(TraitMethod::class)]
#[UsesClass(SourceParser::class)]
#[Small]
final class TraitFlatteningTest extends TestCase
{
    public function testRenamesReadsWhatAUseStatementApplies(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Taker { use Shared { shared as renamed; } }\n") ?? []);
        $use = (new NodeFinder())->findFirstInstanceOf($parsed, TraitUse::class);
        self::assertNotNull($use);

        self::assertSame(['shared' => 'renamed'], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testRenamesLeavesOutOneWrittenForAnotherTrait(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Taker { use Shared, Other { Other::shared as renamed; } }\n") ?? []);
        $use = (new NodeFinder())->findFirstInstanceOf($parsed, TraitUse::class);
        self::assertNotNull($use);

        self::assertSame([], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testRenamesAppliesOneWrittenForNoTraitInParticular(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Taker { use Shared { shared as renamed; } }\n") ?? []);
        $use = (new NodeFinder())->findFirstInstanceOf($parsed, TraitUse::class);
        self::assertNotNull($use);

        self::assertSame(['shared' => 'renamed'], TraitFlattening::renames($use, 'App\Other'));
    }

    public function testRenamesLeavesOutAnAdaptationThatRenamesNothing(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Taker { use Shared { shared as protected; } }\n") ?? []);
        $use = (new NodeFinder())->findFirstInstanceOf($parsed, TraitUse::class);
        self::assertNotNull($use);

        self::assertSame([], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testRenamesReadsAMethodNameWhateverCasingTheAdaptationWritesIt(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Taker { use Shared { SHARED as renamed; } }\n") ?? []);
        $use = (new NodeFinder())->findFirstInstanceOf($parsed, TraitUse::class);
        self::assertNotNull($use);

        self::assertSame(['shared' => 'renamed'], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testRenamesReadsATraitNameWhateverCasingTheAdaptationWritesIt(): void
    {
        $parsed = (new NodeTraverser(new NameResolver()))->traverse((new ParserFactory())->createForHostVersion()->parse("<?php\nnamespace App;\nclass Taker { use Shared { \\App\\SHARED::shared as renamed; } }\n") ?? []);
        $use = (new NodeFinder())->findFirstInstanceOf($parsed, TraitUse::class);
        self::assertNotNull($use);

        self::assertSame(['shared' => 'renamed'], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testMethodsOfReadsWhatATraitWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function written(): void {} abstract public function demanded(): void; }\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertEquals(
            ['written' => new TraitMethod('App\Shared', false), 'demanded' => new TraitMethod('App\Shared', true)],
            TraitFlattening::methodsOf('App\Shared', [], $index, []),
        );
    }

    public function testMethodsOfReadsWhatATraitTakesOnFromAnotherTrait(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Inner { public function inner(): void {} }\ntrait Outer { use Inner; }\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertEquals(['inner' => new TraitMethod('App\Inner', false)], TraitFlattening::methodsOf('App\Outer', [], $index, []));
    }

    public function testMethodsOfReadsNothingFromATraitNoAnalysedFileDeclares(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Shared {}\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertSame([], TraitFlattening::methodsOf('App\Missing', [], $index, []));
    }

    public function testMethodsOfReadsNothingFromATraitAlreadyBeingRead(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertSame([], TraitFlattening::methodsOf('App\Shared', [], $index, ['app\shared']));
    }

    public function testMethodsOfReadsAMethodUnderTheNameItAnswersTo(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function Mixed_Case(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertEquals(['mixed_case' => new TraitMethod('App\Shared', false)], TraitFlattening::methodsOf('App\Shared', [], $index, []));
    }

    public function testMethodsOfFindsATraitWhateverCasingItIsAskedFor(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertEquals(['shared' => new TraitMethod('App\Shared', false)], TraitFlattening::methodsOf('app\shared', [], $index, []));
    }

    public function testMethodsOfStopsAtATraitAlreadyBeingReadWhateverCasingItIsNamedIn(): void
    {
        $root = vfsStream::setup('project', null, ['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Shared.php'], $root->url());

        self::assertSame([], TraitFlattening::methodsOf('APP\SHARED', [], $index, ['app\shared']));
    }

    public function testKeepsMethodKeepsTheCopyOfAMethodTheClassDoesNotWrite(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Shared')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertTrue(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Shared', $index));
    }

    public function testKeepsMethodDiscardsTheCopyOfAMethodTheClassWritesItself(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; public function shared(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Shared')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Shared', $index));
    }

    public function testKeepsMethodDiscardsACopyOfAMethodTheClassWritesUnderAnotherCasing(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; public function SHARED(): void {} }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Shared')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Shared', $index));
    }

    public function testKeepsMethodDiscardsTheCopyOfATraitAnInsteadofRulesOut(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right { Left::shared insteadof Right; } }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Right')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Right', $index));
    }

    public function testKeepsMethodKeepsTheCopyOfTheTraitAnInsteadofNames(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right { Left::shared insteadof Right; } }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Left')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertTrue(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Left', $index));
    }

    public function testKeepsMethodDiscardsADemandedMethodAnotherTraitWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Demanding { abstract public function shared(): void; }\ntrait Writing { public function shared(): void {} }\nclass Taker { use Demanding, Writing; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Demanding')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Demanding', $index));
    }

    public function testKeepsMethodDiscardsADemandedMethodAnAncestorWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Demanding { abstract public function shared(): void; }\nclass Ancestor_ { public function shared(): void {} }\nclass Taker extends Ancestor_ { use Demanding; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Demanding')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Demanding', $index));
    }

    public function testKeepsMethodKeepsADemandedMethodNothingAnswers(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Demanding { abstract public function shared(): void; }\nabstract class Taker { use Demanding; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Demanding')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertTrue(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Demanding', $index));
    }

    public function testKeepsMethodKeepsACopyTheTraitWritesWhateverCasingTheTraitIsNamedIn(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        $method = $index->classLike('App\Shared')?->node->getMethod('shared');
        self::assertNotNull($class);
        self::assertNotNull($method);

        self::assertTrue(TraitFlattening::keepsMethod($class, $method, 'shared', 'app\shared', $index));
    }

    public function testProviderOfNamesNoTraitForAMethodNoneOffers(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertNull(TraitFlattening::providerOf($class, 'missing', $index));
    }

    public function testProviderOfFindsAMethodWhateverCasingItIsAskedFor(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertSame('App\Shared', TraitFlattening::providerOf($class, 'SHARED', $index));
    }

    public function testProviderOfIsSettledByAnInsteadofWhateverCasingItWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right { \\App\\RIGHT::SHARED insteadof Left; } }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertSame('App\Right', TraitFlattening::providerOf($class, 'shared', $index));
    }

    public function testOffersOfReadsWhatTheTraitsAClassUsesOffer(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right; }\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertEquals(
            [new TraitMethod('App\Left', false), new TraitMethod('App\Right', false)],
            TraitFlattening::offersOf($class, 'shared', $index),
        );
    }

    public function testWritesMethodReportsAMethodAClassWritesItself(): void
    {
        $class = (new NodeFinder())->findFirstInstanceOf((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Taker { public function written(): void {} }\n") ?? [], Class_::class);
        self::assertNotNull($class);

        self::assertTrue(TraitFlattening::writesMethod($class, 'WRITTEN'));
    }

    public function testWritesMethodReportsNoMethodOfAnUnwrittenName(): void
    {
        $class = (new NodeFinder())->findFirstInstanceOf((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Taker { public function written(): void {} }\n") ?? [], Class_::class);
        self::assertNotNull($class);

        self::assertFalse(TraitFlattening::writesMethod($class, 'other'));
    }

    public function testMethodWrittenFindsTheMethodAClassWrites(): void
    {
        $class = (new NodeFinder())->findFirstInstanceOf((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Taker { public function written(): void {} }\n") ?? [], Class_::class);
        self::assertNotNull($class);

        self::assertSame($class->stmts[0], TraitFlattening::methodWritten($class, 'WRITTEN'));
    }

    public function testMethodWrittenFindsNothingForANameTheClassDoesNotWrite(): void
    {
        $class = (new NodeFinder())->findFirstInstanceOf((new ParserFactory())->createForHostVersion()->parse("<?php\nclass Taker { public function written(): void {} }\n") ?? [], Class_::class);
        self::assertNotNull($class);

        self::assertNull(TraitFlattening::methodWritten($class, 'other'));
    }

    public function testInheritsFindsNothingAboveAClassWithNoParent(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\nclass Taker {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertFalse(TraitFlattening::inherits($class, 'shared', $index, []));
    }

    public function testInheritsFindsNothingAboveAClassWhoseParentIsNotAnalysed(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\nclass Taker extends \\RuntimeException {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertFalse(TraitFlattening::inherits($class, 'getMessage', $index, []));
    }

    public function testInheritsFindsAMethodWrittenTwoClassesAbove(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\nclass Root { public function shared(): void {} }\nclass Middle extends Root {}\nclass Taker extends Middle {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertTrue(TraitFlattening::inherits($class, 'shared', $index, []));
    }

    public function testInheritsFindsNoMethodWhereTheAncestorOnlyDemandsOne(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\nabstract class Root { abstract public function shared(): void; }\nclass Taker extends Root {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertFalse(TraitFlattening::inherits($class, 'shared', $index, []));
    }

    public function testInheritsFindsAMethodWrittenAboveUnderAnotherCasing(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\nclass Root { public function SHARED(): void {} }\nclass Taker extends Root {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertTrue(TraitFlattening::inherits($class, 'shared', $index, []));
    }

    public function testInheritsFindsAMethodATraitOfAnAncestorWrites(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\ntrait Writing { public function shared(): void {} }\nclass Root { use Writing; }\nclass Taker extends Root {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertTrue(TraitFlattening::inherits($class, 'shared', $index, []));
    }

    public function testInheritsStopsAtAnAncestorAlreadyBeingRead(): void
    {
        $root = vfsStream::setup('project', null, ['Taker.php' => "<?php\nnamespace App;\nclass Root { public function shared(): void {} }\nclass Taker extends Root {}\n"]);
        $index = SourceIndex::of([$root->url().'/Taker.php'], $root->url());
        $class = $index->classLike('App\Taker')?->node;
        self::assertNotNull($class);

        self::assertFalse(TraitFlattening::inherits($class, 'shared', $index, ['app\root']));
    }
}
