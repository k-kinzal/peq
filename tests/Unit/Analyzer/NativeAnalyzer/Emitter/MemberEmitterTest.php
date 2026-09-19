<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer\Emitter;

use App\Analyzer\NativeAnalyzer\Emitter\MemberEmitter;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GraphSpelling;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(MemberEmitter::class)]
#[Medium]
final class MemberEmitterTest extends TestCase
{
    /**
     * @param list<string> $expected What the statement is expected to record
     */
    #[DataProvider('providerProperties')]
    public function testPropertiesRecordsEveryOneAStatementDeclares(string $code, array $expected): void
    {
        $statement = ParsedSnippet::memberStatement($code, Property::class);

        self::assertSame($expected, GraphSpelling::of(MemberEmitter::properties($statement, ParsedSnippet::scopeIn('App\Invoice', null))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerProperties(): iterable
    {
        yield 'one property' => [
            "<?php\nclass Written { public int \$held = 0; }\n",
            ['property App\Invoice::held', 'App\Invoice -[declaration-property]-> App\Invoice::held'],
        ];

        yield 'two properties in one statement' => [
            "<?php\nclass Written { public \$first, \$second; }\n",
            [
                'property App\Invoice::first', 'App\Invoice -[declaration-property]-> App\Invoice::first',
                'property App\Invoice::second', 'App\Invoice -[declaration-property]-> App\Invoice::second',
            ],
        ];

        yield 'a property typed as a class' => [
            "<?php\nclass Written { public \\App\\Money \$held; }\n",
            [
                'property App\Invoice::held',
                'App\Invoice -[declaration-property]-> App\Invoice::held',
                'App\Invoice::held -[declaration-type-property]-> App\Money',
            ],
        ];

        yield 'a property carrying an attribute' => [
            "<?php\nclass Written { #[\\App\\Marker] public int \$held = 0; }\n",
            [
                'property App\Invoice::held',
                'App\Invoice -[declaration-property]-> App\Invoice::held',
                'App\Invoice::held -[attribute]-> App\Marker',
            ],
        ];
    }

    /**
     * @param list<string> $expected What the statement is expected to record
     */
    #[DataProvider('providerConstants')]
    public function testConstantsRecordsEveryOneAStatementDeclares(string $code, array $expected): void
    {
        $statement = ParsedSnippet::memberStatement($code, ClassConst::class);

        self::assertSame($expected, GraphSpelling::of(MemberEmitter::constants($statement, ParsedSnippet::scopeIn('App\Invoice', null))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerConstants(): iterable
    {
        yield 'one constant' => [
            "<?php\nclass Written { public const KIND = 'written'; }\n",
            ['constant App\Invoice::KIND', 'App\Invoice -[declaration-constant]-> App\Invoice::KIND'],
        ];

        yield 'two constants in one statement' => [
            "<?php\nclass Written { public const FIRST = 1, SECOND = 2; }\n",
            [
                'constant App\Invoice::FIRST', 'App\Invoice -[declaration-constant]-> App\Invoice::FIRST',
                'constant App\Invoice::SECOND', 'App\Invoice -[declaration-constant]-> App\Invoice::SECOND',
            ],
        ];

        yield 'a constant carrying an attribute' => [
            "<?php\nclass Written { #[\\App\\Marker] public const KIND = 'written'; }\n",
            [
                'constant App\Invoice::KIND',
                'App\Invoice -[declaration-constant]-> App\Invoice::KIND',
                'App\Invoice::KIND -[attribute]-> App\Marker',
            ],
        ];
    }

    public function testEnumCaseRecordsTheCaseAnEnumDeclares(): void
    {
        $statement = ParsedSnippet::memberStatement("<?php\nenum Status { case Open; }\n", EnumCase::class);

        self::assertSame(
            ['enum_case App\Invoice::Open', 'App\Invoice -[declaration-enum-case]-> App\Invoice::Open'],
            GraphSpelling::of(MemberEmitter::enumCase($statement, ParsedSnippet::scopeIn('App\Invoice', null))),
        );
    }

    public function testPropertiesRecordsNothingOutsideAClass(): void
    {
        $statement = ParsedSnippet::memberStatement("<?php\nclass Written { public int \$held = 0; }\n", Property::class);

        self::assertSame([], MemberEmitter::properties($statement, ParsedSnippet::scopeIn(null, null)));
    }

    public function testConstantsRecordsNothingOutsideAClass(): void
    {
        $statement = ParsedSnippet::memberStatement("<?php\nclass Written { public const KIND = 'written'; }\n", ClassConst::class);

        self::assertSame([], MemberEmitter::constants($statement, ParsedSnippet::scopeIn(null, null)));
    }

    public function testEnumCaseRecordsNothingOutsideAClass(): void
    {
        $statement = ParsedSnippet::memberStatement("<?php\nenum Status { case Open; }\n", EnumCase::class);

        self::assertSame([], MemberEmitter::enumCase($statement, ParsedSnippet::scopeIn(null, null)));
    }
}
