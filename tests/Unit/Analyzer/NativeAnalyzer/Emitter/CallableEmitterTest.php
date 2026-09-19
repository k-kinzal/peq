<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\Emitter\CallableEmitter;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GraphSpelling;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(CallableEmitter::class)]
#[Medium]
final class CallableEmitterTest extends TestCase
{
    /**
     * @param list<string> $expected What the declaration is expected to record
     */
    #[DataProvider('providerMethods')]
    public function testMethodRecordsADeclarationAndWhatItsSignatureCommitsTo(string $code, array $expected): void
    {
        self::assertSame($expected, GraphSpelling::of(CallableEmitter::method(ParsedSnippet::method($code), ParsedSnippet::scopeIn('App\Invoice', null), NodeKind::Klass)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerMethods(): iterable
    {
        yield 'a method' => [
            "<?php\nclass Written { public function total(): void {} }\n",
            ['App\Invoice -[declaration-method]-> App\Invoice::total', 'method App\Invoice::total'],
        ];

        yield 'a method that returns a class' => [
            "<?php\nclass Written { public function total(): \\App\\Money {} }\n",
            [
                'App\Invoice -[declaration-method]-> App\Invoice::total',
                'method App\Invoice::total',
                'App\Invoice::total -[declaration-type-return]-> App\Money',
            ],
        ];

        yield 'a method that takes a class' => [
            "<?php\nclass Written { public function total(\\App\\Money \$money): void {} }\n",
            [
                'App\Invoice -[declaration-method]-> App\Invoice::total',
                'method App\Invoice::total',
                'App\Invoice::total -[declaration-type-parameter]-> App\Money',
            ],
        ];

        yield 'a method carrying an attribute' => [
            "<?php\nclass Written { #[\\App\\Marker] public function total(): void {} }\n",
            [
                'App\Invoice -[declaration-method]-> App\Invoice::total',
                'method App\Invoice::total',
                'App\Invoice::total -[attribute]-> App\Marker',
            ],
        ];

        yield 'a method whose parameter carries an attribute' => [
            "<?php\nclass Written { public function total(#[\\App\\Marker] int \$amount): void {} }\n",
            [
                'App\Invoice -[declaration-method]-> App\Invoice::total',
                'method App\Invoice::total',
                'App\Invoice::total -[attribute]-> App\Marker',
            ],
        ];

        yield 'a method whose signature names only builtin types' => [
            "<?php\nclass Written { public function total(int \$amount): string {} }\n",
            ['App\Invoice -[declaration-method]-> App\Invoice::total', 'method App\Invoice::total'],
        ];
    }

    public function testMethodRecordsADeclarationAgainstTheKindOfWhatDeclaresIt(): void
    {
        $recorded = CallableEmitter::method(ParsedSnippet::method("<?php\ninterface Written { public function total(): void; }\n"), ParsedSnippet::scopeIn('App\Invoice', null), NodeKind::Interface);

        self::assertSame(['App\Invoice -[declaration-method]-> App\Invoice::total', 'method App\Invoice::total'], GraphSpelling::of($recorded));
    }

    public function testMethodRecordsNothingOutsideAClass(): void
    {
        self::assertSame([], CallableEmitter::method(ParsedSnippet::method("<?php\nclass Written { public function total(): void {} }\n"), ParsedSnippet::scopeIn(null, null), NodeKind::Klass));
    }

    /**
     * @param list<string> $expected What the declaration is expected to record
     */
    #[DataProvider('providerFunctions')]
    public function testGlobalFunctionRecordsADeclarationAndWhatItsSignatureCommitsTo(string $code, array $expected): void
    {
        $declaration = ParsedSnippet::memberStatement($code, Function_::class);

        self::assertSame($expected, GraphSpelling::of(CallableEmitter::globalFunction($declaration, ParsedSnippet::scopeIn(null, null))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerFunctions(): iterable
    {
        yield 'a function' => ["<?php\nnamespace App;\nfunction helper(): void {}\n", ['function App\helper']];

        yield 'a function that returns a class' => [
            "<?php\nnamespace App;\nfunction helper(): \\App\\Money {}\n",
            ['function App\helper', 'App\helper -[declaration-type-return]-> App\Money'],
        ];
    }

    /**
     * @param list<string> $expected What the declaration is expected to record
     */
    #[DataProvider('providerPromotedParameters')]
    public function testPromotedPropertyRecordsThePropertyAParameterDeclares(string $code, array $expected): void
    {
        $param = ParsedSnippet::parameter($code);

        self::assertSame($expected, GraphSpelling::of(CallableEmitter::promotedProperty($param, ParsedSnippet::scopeIn('App\Invoice', null))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerPromotedParameters(): iterable
    {
        yield 'a parameter with a visibility declares a property' => [
            "<?php\nclass Written { public function __construct(private int \$held) {} }\n",
            ['property App\Invoice::held', 'App\Invoice -[declaration-property]-> App\Invoice::held'],
        ];

        yield 'a parameter typed as a class' => [
            "<?php\nclass Written { public function __construct(private \\App\\Money \$held) {} }\n",
            [
                'property App\Invoice::held',
                'App\Invoice -[declaration-property]-> App\Invoice::held',
                'App\Invoice::held -[declaration-type-property]-> App\Money',
            ],
        ];

        yield 'a parameter carrying an attribute' => [
            "<?php\nclass Written { public function __construct(#[\\App\\Marker] private int \$held) {} }\n",
            [
                'property App\Invoice::held',
                'App\Invoice -[declaration-property]-> App\Invoice::held',
                'App\Invoice::held -[attribute]-> App\Marker',
            ],
        ];

        yield 'a parameter with no visibility declares nothing' => ["<?php\nclass Written { public function total(int \$amount): void {} }\n", []];
    }

    public function testPromotedPropertyRecordsNothingOutsideAClass(): void
    {
        $param = ParsedSnippet::parameter("<?php\nclass Written { public function __construct(private int \$held) {} }\n");

        self::assertSame([], CallableEmitter::promotedProperty($param, ParsedSnippet::scopeIn(null, null)));
    }

    public function testSignatureRecordsTheTypesADeclarationCommitsTo(): void
    {
        $declaration = ParsedSnippet::method("<?php\nclass Written { public function total(): \\App\\Money {} }\n");
        $declared = ParsedSnippet::writtenBy('App\Invoice', 'total');
        $recorded = CallableEmitter::signature($declaration, $declared, ParsedSnippet::scopeIn('App\Invoice', null), ParsedSnippet::writtenAt());

        self::assertSame(['App\Invoice::total -[declaration-type-return]-> App\Money'], GraphSpelling::of($recorded));
    }

    public function testSignatureRecordsNothingForASignatureThatNamesOnlyBuiltinTypes(): void
    {
        $declaration = ParsedSnippet::method("<?php\nclass Written { public function total(int \$amount): string {} }\n");
        $declared = ParsedSnippet::writtenBy('App\Invoice', 'total');

        self::assertSame([], CallableEmitter::signature($declaration, $declared, ParsedSnippet::scopeIn('App\Invoice', null), ParsedSnippet::writtenAt()));
    }
}
