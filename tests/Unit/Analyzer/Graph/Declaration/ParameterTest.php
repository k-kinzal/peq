<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\Declaration;

use App\Analyzer\Graph\Declaration\Parameter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Parameter::class)]
#[Small]
final class ParameterTest extends TestCase
{
    public function testAParameterKeepsEverythingItsDeclarationWrote(): void
    {
        $parameter = new Parameter('rows', 'string', optional: true, variadic: true, byRef: true, promoted: true);

        self::assertSame('rows', $parameter->name);
        self::assertSame('string', $parameter->type);
        self::assertTrue($parameter->optional);
        self::assertTrue($parameter->variadic);
        self::assertTrue($parameter->byRef);
        self::assertTrue($parameter->promoted);
    }

    public function testAParameterWrittenWithoutATypeHasNone(): void
    {
        self::assertNull((new Parameter('value'))->type);
    }

    #[DataProvider('providerParametersAsTheyAreWritten')]
    public function testToStringWritesTheParameterTheWayItsDeclarationWritesIt(Parameter $parameter, string $written): void
    {
        self::assertSame($written, $parameter->toString());
    }

    /**
     * @return iterable<string, array{Parameter, string}>
     */
    public static function providerParametersAsTheyAreWritten(): iterable
    {
        yield 'typed' => [new Parameter('amount', 'int'), 'int $amount'];

        yield 'untyped' => [new Parameter('value'), '$value'];

        yield 'variadic' => [new Parameter('rows', 'string', variadic: true), 'string ...$rows'];

        yield 'by reference' => [new Parameter('carry', 'array', byRef: true), 'array &$carry'];

        yield 'by reference and variadic' => [new Parameter('rest', null, byRef: true, variadic: true), '&...$rest'];

        yield 'optional, whose default is not part of how it is called' => [new Parameter('limit', 'int', optional: true), 'int $limit'];
    }
}
