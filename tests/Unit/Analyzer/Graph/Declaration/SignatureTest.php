<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Declaration;

use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Signature::class)]
#[UsesClass(Parameter::class)]
#[Small]
final class SignatureTest extends TestCase
{
    public function testASignatureKeepsTheParametersItWasBuiltWith(): void
    {
        $amount = new Parameter('amount', 'int');

        self::assertSame([$amount], (new Signature([$amount]))->parameters);
    }

    public function testADeclarationThatPromisesNothingAboutItsResultHasNoReturnType(): void
    {
        self::assertNull((new Signature())->returnType);
    }

    #[DataProvider('providerSignaturesAsTheyAreWritten')]
    public function testToStringWritesTheSignatureTheWayItsDeclarationWritesIt(Signature $signature, string $written): void
    {
        self::assertSame($written, $signature->toString());
    }

    /**
     * @return iterable<string, array{Signature, string}>
     */
    public static function providerSignaturesAsTheyAreWritten(): iterable
    {
        yield 'taking and giving nothing' => [new Signature(), '()'];

        yield 'giving something' => [new Signature([], 'void'), '(): void'];

        yield 'taking one thing' => [new Signature([new Parameter('amount', 'int')], 'int'), '(int $amount): int'];

        yield 'taking several' => [
            new Signature([new Parameter('amount', 'int'), new Parameter('currency', 'string')]),
            '(int $amount, string $currency)',
        ];
    }

    public function testParameterNamesAreTheNamesANamedArgumentWouldUse(): void
    {
        $signature = new Signature([new Parameter('amount', 'int'), new Parameter('currency', 'string')]);

        self::assertSame(['amount', 'currency'], $signature->parameterNames());
    }

    public function testParameterNamesOfASignatureThatTakesNothingAreNone(): void
    {
        self::assertSame([], (new Signature())->parameterNames());
    }

    public function testParameterTypesKeepThePlaceOfAnUntypedParameter(): void
    {
        $signature = new Signature([new Parameter('amount', 'int'), new Parameter('value')]);

        self::assertSame(['int', ''], $signature->parameterTypes());
    }
}
