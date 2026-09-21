<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration;

use App\Analyzer\Declaration\TypeText;
use App\Analyzer\Declaration\WrittenSignature;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WrittenSignature::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(TypeText::class)]
#[Small]
final class WrittenSignatureTest extends TestCase
{
    public function testOfReadsTheSignatureTheDeclarationWrites(): void
    {
        $written = new ClassMethod('total', [
            'params' => [new Param(new Variable('rate'), type: new Identifier('int'))],
            'returnType' => new Identifier('int'),
        ]);

        self::assertSame('(int $rate): int', WrittenSignature::of($written)->toString());
    }

    public function testOfReadsADeclarationThatPromisesNothingAboutItsResult(): void
    {
        self::assertNull(WrittenSignature::of(new Function_('total'))->returnType);
    }

    public function testOfLeavesOutAParameterThatHasNoNameToRecord(): void
    {
        $written = new Function_('total', [
            'params' => [new Param(new Variable(new Variable('chosen')))],
        ]);

        self::assertSame([], WrittenSignature::of($written)->parameters);
    }

    public function testParameterReadsWhatItsDeclarationWrote(): void
    {
        $written = new Param(
            new Variable('rows'),
            default: new Int_(0),
            type: new Identifier('int'),
            byRef: true,
            variadic: true,
        );
        $parameter = WrittenSignature::parameter($written);

        self::assertNotNull($parameter);
        self::assertSame('rows', $parameter->name);
        self::assertSame('int', $parameter->type);
        self::assertTrue($parameter->optional);
        self::assertTrue($parameter->variadic);
        self::assertTrue($parameter->byRef);
    }

    public function testParameterReportsThatAPromotedParameterAlsoDeclaresAProperty(): void
    {
        $written = new Param(new Variable('amount'), type: new Identifier('int'), flags: Modifiers::PRIVATE);
        $parameter = WrittenSignature::parameter($written);

        self::assertNotNull($parameter);
        self::assertTrue($parameter->promoted);
    }

    public function testParameterOfSomethingWithNoNameIsNothing(): void
    {
        self::assertNull(WrittenSignature::parameter(new Param(new Variable(new Variable('chosen')))));
    }
}
