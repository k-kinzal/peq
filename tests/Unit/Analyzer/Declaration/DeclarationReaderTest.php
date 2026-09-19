<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration;

use App\Analyzer\Declaration\DeclarationReader;
use App\Analyzer\Declaration\ExpressionText;
use App\Analyzer\Declaration\TypeText;
use App\Analyzer\Declaration\WrittenSignature;
use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers as WrittenModifiers;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DeclarationReader::class)]
#[UsesClass(AttributeUsage::class)]
#[UsesClass(Modifiers::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(ExpressionText::class)]
#[UsesClass(TypeText::class)]
#[UsesClass(WrittenSignature::class)]
#[Small]
final class DeclarationReaderTest extends TestCase
{
    public function testForClassLikeReadsTheKeywordsWrittenOnAClass(): void
    {
        $written = new Class_('Invoice', ['flags' => WrittenModifiers::FINAL | WrittenModifiers::READONLY]);
        $declared = DeclarationReader::forClassLike($written, []);

        self::assertTrue($declared->modifiers->final);
        self::assertTrue($declared->modifiers->readonly);
        self::assertFalse($declared->modifiers->abstract);
    }

    public function testForClassLikeReadsAnInterfaceAsCarryingNoKeyword(): void
    {
        self::assertTrue(DeclarationReader::forClassLike(new Interface_('Payable'), [])->modifiers->none());
    }

    public function testForClassLikeKeepsTheAttributesItIsGiven(): void
    {
        $route = new AttributeUsage('App\Http\Route');

        self::assertSame([$route], DeclarationReader::forClassLike(new Class_('Invoice'), [$route])->attributes);
    }

    public function testForCallableReadsTheVisibilityWrittenOnAMethod(): void
    {
        $written = new ClassMethod('total', ['flags' => WrittenModifiers::PROTECTED]);

        self::assertSame(Visibility::Protected, DeclarationReader::forCallable($written, [])->visibility);
    }

    public function testForCallableReadsTheKeywordsWrittenOnAMethod(): void
    {
        $written = new ClassMethod('total', ['flags' => WrittenModifiers::STATIC | WrittenModifiers::FINAL]);
        $declared = DeclarationReader::forCallable($written, []);

        self::assertTrue($declared->modifiers->static);
        self::assertTrue($declared->modifiers->final);
        self::assertFalse($declared->modifiers->abstract);
    }

    public function testForCallableReadsAFunctionAsCarryingNoVisibility(): void
    {
        $declared = DeclarationReader::forCallable(new Function_('formatMoney'), []);

        self::assertNull($declared->visibility);
        self::assertTrue($declared->modifiers->none());
    }

    public function testForCallableReadsTheSignatureItsCallersAreWrittenAgainst(): void
    {
        $written = new ClassMethod('total', [
            'params' => [new Param(new Variable('rate'), type: new Identifier('int'))],
            'returnType' => new Identifier('int'),
        ]);

        self::assertSame('(int $rate): int', DeclarationReader::forCallable($written, [])->signature?->toString());
    }

    public function testForPropertyReadsTheKeywordsAndTypeOfTheStatement(): void
    {
        $written = new Property(
            WrittenModifiers::PRIVATE | WrittenModifiers::READONLY,
            [new PropertyItem('amount')],
            type: new Identifier('int'),
        );
        $declared = DeclarationReader::forProperty($written, []);

        self::assertSame(Visibility::Private, $declared->visibility);
        self::assertTrue($declared->modifiers->readonly);
        self::assertSame('int', $declared->type);
    }

    public function testForPromotedPropertyReadsTheVisibilityThatPromotesIt(): void
    {
        $written = new Param(
            new Variable('amount'),
            default: new Int_(0),
            type: new Identifier('int'),
            flags: WrittenModifiers::PROTECTED | WrittenModifiers::READONLY,
        );
        $declared = DeclarationReader::forPromotedProperty($written, []);

        self::assertSame(Visibility::Protected, $declared->visibility);
        self::assertTrue($declared->modifiers->readonly);
        self::assertSame('int', $declared->type);
        self::assertSame('0', $declared->value);
    }

    public function testForConstantReadsTheValueOfTheOneConstantItDescribes(): void
    {
        $first = new Const_('RATE', new Int_(3));
        $second = new Const_('SCALE', new Int_(4));
        $statement = new ClassConst([$first, $second], WrittenModifiers::PRIVATE | WrittenModifiers::FINAL);
        $declared = DeclarationReader::forConstant($statement, $second, []);

        self::assertSame('4', $declared->value);
        self::assertSame(Visibility::Private, $declared->visibility);
        self::assertTrue($declared->modifiers->final);
    }

    public function testForEnumCaseReadsTheValueThatBacksIt(): void
    {
        self::assertSame("'draft'", DeclarationReader::forEnumCase(new EnumCase('Draft', new String_('draft')), [])->value);
    }

    public function testForEnumCaseOfAPureEnumReadsNoValue(): void
    {
        self::assertNull(DeclarationReader::forEnumCase(new EnumCase('Draft'), [])->value);
    }

    #[DataProvider('providerVisibilitiesAsTheyAreAsked')]
    public function testVisibilityNamesTheLevelADeclarationCarries(bool $private, bool $protected, Visibility $named): void
    {
        self::assertSame($named, DeclarationReader::visibility($private, $protected));
    }

    /**
     * @return iterable<string, array{bool, bool, Visibility}>
     */
    public static function providerVisibilitiesAsTheyAreAsked(): iterable
    {
        yield 'written private' => [true, false, Visibility::Private];

        yield 'written protected' => [false, true, Visibility::Protected];

        yield 'written with no keyword' => [false, false, Visibility::Public];
    }

    public function testDeprecatedReportsADocBlockThatMarksTheDeclaration(): void
    {
        $written = new Class_('Invoice');
        $written->setDocComment(new Doc("/**\n * Superseded by Billing.\n *\n * @deprecated since 2.0\n */"));

        self::assertTrue(DeclarationReader::deprecated($written));
    }

    public function testDeprecatedReadsProseThatMentionsTheWordAsProse(): void
    {
        $written = new Class_('Invoice');
        $written->setDocComment(new Doc("/**\n * This is not deprecated at all.\n */"));

        self::assertFalse(DeclarationReader::deprecated($written));
    }

    public function testDeprecatedReportsNothingAboutADeclarationWithNoDocBlock(): void
    {
        self::assertFalse(DeclarationReader::deprecated(new Class_('Invoice')));
    }
}
