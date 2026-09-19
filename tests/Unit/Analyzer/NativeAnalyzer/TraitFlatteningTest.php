<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\TraitFlattening;
use App\Analyzer\NativeAnalyzer\TraitMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(TraitFlattening::class)]
#[UsesClass(\App\Analyzer\NativeAnalyzer\SourceIndex::class)]
#[UsesClass(\App\Analyzer\NativeAnalyzer\AnonymousClassNaming::class)]
#[UsesClass(\App\Analyzer\NativeAnalyzer\AutoloadIndex::class)]
#[UsesClass(\App\Analyzer\NativeAnalyzer\ClassLikeDeclaration::class)]
#[UsesClass(\App\Analyzer\NativeAnalyzer\ParsedSource::class)]
#[UsesClass(TraitMethod::class)]
#[Small]
final class TraitFlatteningTest extends TestCase
{
    public function testRenamesReadsWhatAUseStatementApplies(): void
    {
        $use = ParsedSnippet::traitUse("<?php\nnamespace App;\nclass Taker { use Shared { shared as renamed; } }\n");

        self::assertSame(['shared' => 'renamed'], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testRenamesLeavesOutOneWrittenForAnotherTrait(): void
    {
        $use = ParsedSnippet::traitUse("<?php\nnamespace App;\nclass Taker { use Shared, Other { Other::shared as renamed; } }\n");

        self::assertSame([], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testRenamesAppliesOneWrittenForNoTraitInParticular(): void
    {
        $use = ParsedSnippet::traitUse("<?php\nnamespace App;\nclass Taker { use Shared { shared as renamed; } }\n");

        self::assertSame(['shared' => 'renamed'], TraitFlattening::renames($use, 'App\Other'));
    }

    public function testRenamesLeavesOutAnAdaptationThatRenamesNothing(): void
    {
        $use = ParsedSnippet::traitUse("<?php\nnamespace App;\nclass Taker { use Shared { shared as protected; } }\n");

        self::assertSame([], TraitFlattening::renames($use, 'App\Shared'));
    }

    public function testMethodsOfReadsWhatATraitWrites(): void
    {
        $index = ParsedSnippet::index(['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function written(): void {} abstract public function demanded(): void; }\n"]);
        $methods = TraitFlattening::methodsOf('App\Shared', [], $index, []);

        self::assertSame([false, true], [$methods['written']->demanded, $methods['demanded']->demanded]);
    }

    public function testMethodsOfReadsWhatATraitTakesOnFromAnotherTrait(): void
    {
        $index = ParsedSnippet::index(['Shared.php' => "<?php\nnamespace App;\ntrait Inner { public function inner(): void {} }\ntrait Outer { use Inner; }\n"]);

        self::assertSame('App\Inner', TraitFlattening::methodsOf('App\Outer', [], $index, [])['inner']->declaringTrait);
    }

    public function testMethodsOfReadsNothingFromATraitNoAnalysedFileDeclares(): void
    {
        $index = ParsedSnippet::index(['Shared.php' => "<?php\nnamespace App;\ntrait Shared {}\n"]);

        self::assertSame([], TraitFlattening::methodsOf('App\Missing', [], $index, []));
    }

    public function testMethodsOfReadsNothingFromATraitAlreadyBeingRead(): void
    {
        $index = ParsedSnippet::index(['Shared.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\n"]);

        self::assertSame([], TraitFlattening::methodsOf('App\Shared', [], $index, ['app\shared']));
    }

    public function testKeepsMethodKeepsTheCopyOfAMethodTheClassDoesNotWrite(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');

        self::assertTrue(TraitFlattening::keepsMethod($class, ParsedSnippet::method("<?php\ntrait Shared { public function shared(): void {} }\n"), 'shared', 'App\Shared', $index));
    }

    public function testKeepsMethodDiscardsTheCopyOfAMethodTheClassWritesItself(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; public function shared(): void {} }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');

        self::assertFalse(TraitFlattening::keepsMethod($class, ParsedSnippet::method("<?php\ntrait Shared { public function shared(): void {} }\n"), 'shared', 'App\Shared', $index));
    }

    public function testKeepsMethodDiscardsTheCopyOfATraitAnInsteadofRulesOut(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right { Left::shared insteadof Right; } }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');
        $method = ParsedSnippet::method("<?php\ntrait Right { public function shared(): void {} }\n");

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Right', $index));
    }

    public function testKeepsMethodKeepsTheCopyOfTheTraitAnInsteadofNames(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right { Left::shared insteadof Right; } }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');
        $method = ParsedSnippet::method("<?php\ntrait Left { public function shared(): void {} }\n");

        self::assertTrue(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Left', $index));
    }

    public function testKeepsMethodDiscardsADemandedMethodAnotherTraitWrites(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Demanding { abstract public function shared(): void; }\ntrait Writing { public function shared(): void {} }\nclass Taker { use Demanding, Writing; }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');
        $method = ParsedSnippet::method("<?php\ntrait Demanding { abstract public function shared(): void; }\n");

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Demanding', $index));
    }

    public function testKeepsMethodDiscardsADemandedMethodAnAncestorWrites(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Demanding { abstract public function shared(): void; }\nclass Ancestor_ { public function shared(): void {} }\nclass Taker extends Ancestor_ { use Demanding; }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');
        $method = ParsedSnippet::method("<?php\ntrait Demanding { abstract public function shared(): void; }\n");

        self::assertFalse(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Demanding', $index));
    }

    public function testKeepsMethodKeepsADemandedMethodNothingAnswers(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Demanding { abstract public function shared(): void; }\nabstract class Taker { use Demanding; }\n"]);
        $class = ParsedSnippet::declarationIn($index, 'App\Taker');
        $method = ParsedSnippet::method("<?php\ntrait Demanding { abstract public function shared(): void; }\n");

        self::assertTrue(TraitFlattening::keepsMethod($class, $method, 'shared', 'App\Demanding', $index));
    }

    public function testProviderOfNamesNoTraitForAMethodNoneOffers(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Shared { public function shared(): void {} }\nclass Taker { use Shared; }\n"]);

        self::assertNull(TraitFlattening::providerOf(ParsedSnippet::declarationIn($index, 'App\Taker'), 'missing', $index));
    }

    public function testWritesMethodReportsAMethodAClassWritesItself(): void
    {
        self::assertTrue(TraitFlattening::writesMethod(ParsedSnippet::classLike("<?php\nclass Taker { public function written(): void {} }\n"), 'WRITTEN'));
    }

    public function testWritesMethodReportsNoMethodOfAnUnwrittenName(): void
    {
        self::assertFalse(TraitFlattening::writesMethod(ParsedSnippet::classLike("<?php\nclass Taker { public function written(): void {} }\n"), 'other'));
    }

    public function testInheritsFindsNothingAboveAClassWithNoParent(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\nclass Taker {}\n"]);

        self::assertFalse(TraitFlattening::inherits(ParsedSnippet::declarationIn($index, 'App\Taker'), 'shared', $index, []));
    }

    public function testInheritsFindsNothingAboveAClassWhoseParentIsNotAnalysed(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\nclass Taker extends \\RuntimeException {}\n"]);

        self::assertFalse(TraitFlattening::inherits(ParsedSnippet::declarationIn($index, 'App\Taker'), 'getMessage', $index, []));
    }

    public function testInheritsFindsAMethodWrittenTwoClassesAbove(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\nclass Root { public function shared(): void {} }\nclass Middle extends Root {}\nclass Taker extends Middle {}\n"]);

        self::assertTrue(TraitFlattening::inherits(ParsedSnippet::declarationIn($index, 'App\Taker'), 'shared', $index, []));
    }

    public function testInheritsFindsNoMethodWhereTheAncestorOnlyDemandsOne(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\nabstract class Root { abstract public function shared(): void; }\nclass Taker extends Root {}\n"]);

        self::assertFalse(TraitFlattening::inherits(ParsedSnippet::declarationIn($index, 'App\Taker'), 'shared', $index, []));
    }

    public function testOffersOfReadsWhatTheTraitsAClassUsesOffer(): void
    {
        $index = ParsedSnippet::index(['Taker.php' => "<?php\nnamespace App;\ntrait Left { public function shared(): void {} }\ntrait Right { public function shared(): void {} }\nclass Taker { use Left, Right; }\n"]);

        self::assertSame(
            ['App\Left', 'App\Right'],
            array_map(static fn (TraitMethod $offered): string => $offered->declaringTrait, TraitFlattening::offersOf(ParsedSnippet::declarationIn($index, 'App\Taker'), 'shared', $index)),
        );
    }
}
