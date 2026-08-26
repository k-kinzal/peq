<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;
use Tests\Contract\Analyzer\DeclarationEdgeContractTest;
use Tests\Contract\Analyzer\UsageEdgeContractTest;

/**
 * What peq claims to read out of each kind of syntax, and where that claim is checked.
 *
 * Three lists have to agree for the analyzer's coverage to mean anything: the syntax
 * that produces relations, the kinds of relation each of them produces, and the
 * contract test that pins each kind down. Writing them here rather than inside the
 * check keeps the claim readable on its own and lets the check state one case at a
 * time instead of walking three tables.
 */
final class EdgeCoverageMap
{
    /**
     * The relation kinds each kind of syntax produces.
     *
     * @var array<class-string, list<EdgeKind>>
     */
    private const NODE_TO_EDGE_KINDS = [
        // ClassLikeProcessor
        Class_::class => [
            EdgeKind::DeclarationExtends,
            EdgeKind::DeclarationImplements,
            EdgeKind::DeclarationTraitUse,
            EdgeKind::Attribute,
        ],
        Interface_::class => [
            EdgeKind::DeclarationExtends,
            EdgeKind::Attribute,
        ],
        Trait_::class => [
            EdgeKind::DeclarationTraitUse,
            EdgeKind::Attribute,
        ],
        Enum_::class => [
            EdgeKind::DeclarationImplements,
            EdgeKind::Attribute,
        ],

        // FunctionLikeProcessor
        ClassMethod::class => [
            EdgeKind::DeclarationMethod,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::Attribute,
        ],
        Function_::class => [
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::Attribute,
        ],

        // PropertyProcessor
        Property::class => [
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationTypeProperty,
            EdgeKind::Attribute,
        ],

        // ClassConstProcessor
        ClassConst::class => [
            EdgeKind::DeclarationConstant,
            EdgeKind::Attribute,
        ],

        // EnumCaseProcessor
        EnumCase::class => [
            EdgeKind::DeclarationEnumCase,
            EdgeKind::Attribute,
        ],

        // PromotedPropertyProcessor
        Param::class => [
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationTypeProperty,
            EdgeKind::Attribute,
        ],

        // Usage processors (via InClassMethodNodeProcessor)
        New_::class => [EdgeKind::Instantiation],
        StaticCall::class => [EdgeKind::StaticCall],
        ClassConstFetch::class => [EdgeKind::ConstFetch],
        Instanceof_::class => [EdgeKind::Instanceof],
        Catch_::class => [EdgeKind::Catch],

        // Known gaps — processor does not exist yet
        FuncCall::class => [EdgeKind::FunctionCall],
        MethodCall::class => [EdgeKind::MethodCall],
        PropertyFetch::class => [EdgeKind::PropertyAccess],
        StaticPropertyFetch::class => [EdgeKind::StaticPropertyAccess],
        NullsafeMethodCall::class => [EdgeKind::MethodCall],
        NullsafePropertyFetch::class => [EdgeKind::PropertyAccess],

        // Type helper nodes — contribute to type-declaration edges
        UnionType::class => [
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeProperty,
        ],
        IntersectionType::class => [
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeProperty,
        ],
        NullableType::class => [
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeProperty,
        ],
    ];

    /**
     * The contract test that pins down each relation kind.
     *
     * @var array<string, class-string>
     */
    private const TESTED_EDGE_KINDS = [
        'declaration-extends' => DeclarationEdgeContractTest::class,
        'declaration-implements' => DeclarationEdgeContractTest::class,
        'declaration-trait-use' => DeclarationEdgeContractTest::class,
        'attribute' => DeclarationEdgeContractTest::class,
        'declaration-type-parameter' => DeclarationEdgeContractTest::class,
        'declaration-type-return' => DeclarationEdgeContractTest::class,
        'declaration-type-property' => DeclarationEdgeContractTest::class,

        'instantiation' => UsageEdgeContractTest::class,
        'static-call' => UsageEdgeContractTest::class,
        'const-fetch' => UsageEdgeContractTest::class,
        'instanceof' => UsageEdgeContractTest::class,
        'catch' => UsageEdgeContractTest::class,
        'function-call' => UsageEdgeContractTest::class,
        'method-call' => UsageEdgeContractTest::class,
        'property-access' => UsageEdgeContractTest::class,
        'static-property-access' => UsageEdgeContractTest::class,
    ];

    /**
     * Relation kinds nothing pins down yet, and why.
     *
     * @var array<string, string>
     */
    private const KNOWN_GAPS = [
        // All edge kinds now have processors and contract tests.
    ];

    /**
     * Kinds peq reads only through `$this`, and what is left out.
     *
     * @var array<string, string>
     */
    private const RECEIVER_LIMITATIONS = [
        'method-call' => 'Only $this->method() — $obj->method() requires PHPStan type inference',
        'property-access' => 'Only $this->prop — $obj->prop requires PHPStan type inference',
    ];

    /**
     * Kinds that say what a symbol declares, which the analysed-output checks pin.
     *
     * @var list<string>
     */
    private const STRUCTURAL_EDGE_KINDS = [
        'declaration-method',
        'declaration-property',
        'declaration-constant',
        'declaration-enum-case',
    ];

    /**
     * Names each kind of syntax mapped to the relations it produces.
     *
     * @return iterable<string, array{class-string, list<EdgeKind>}> One case per syntax kind
     */
    public static function mappedNodes(): iterable
    {
        foreach (self::NODE_TO_EDGE_KINDS as $nodeClass => $kinds) {
            yield $nodeClass => [$nodeClass, $kinds];
        }
    }

    /**
     * Names every relation kind some syntax produces.
     *
     * @return iterable<string, array{string}> One case per relation kind
     */
    public static function mappedEdgeKinds(): iterable
    {
        $seen = [];
        foreach (self::NODE_TO_EDGE_KINDS as $kinds) {
            foreach ($kinds as $kind) {
                $seen[$kind->value] = true;
            }
        }

        foreach (array_keys($seen) as $value) {
            yield $value => [$value];
        }
    }

    /**
     * Names every relation kind a source can write, leaving out derived readings.
     *
     * @return iterable<string, array{EdgeKind}> One case per authored relation kind
     */
    public static function forwardEdgeKinds(): iterable
    {
        foreach (EdgeKind::cases() as $case) {
            if ($case->direction() === \App\Analyzer\Graph\Direction::UsedBy) {
                continue;
            }

            yield $case->value => [$case];
        }
    }

    /**
     * Names each relation kind a contract test pins down, with that test.
     *
     * @return iterable<string, array{string, class-string}> One case per pinned kind
     */
    public static function pinnedEdgeKinds(): iterable
    {
        foreach (self::TESTED_EDGE_KINDS as $value => $testClass) {
            yield $value => [$value, $testClass];
        }
    }

    /**
     * Names each relation kind peq reads only through `$this`.
     *
     * @return iterable<string, array{string}> One case per limited kind
     */
    public static function receiverLimitations(): iterable
    {
        foreach (array_keys(self::RECEIVER_LIMITATIONS) as $value) {
            yield $value => [$value];
        }
    }

    /**
     * Reports the kinds of syntax that are mapped to relations.
     *
     * @return list<class-string> The syntax kinds
     */
    public static function mappedNodeClasses(): array
    {
        return array_keys(self::NODE_TO_EDGE_KINDS);
    }

    /**
     * Reports whether a relation kind is pinned down by a contract test.
     *
     * @param string $kindValue The relation kind
     *
     * @return bool True when a contract test names it
     */
    public static function isTested(string $kindValue): bool
    {
        return isset(self::TESTED_EDGE_KINDS[$kindValue]);
    }

    /**
     * Reports whether a relation kind is a known gap.
     *
     * @param string $kindValue The relation kind
     *
     * @return bool True when nothing pins it down yet
     */
    public static function isKnownGap(string $kindValue): bool
    {
        /** @var array<string, string> $gaps */
        $gaps = self::KNOWN_GAPS;

        return isset($gaps[$kindValue]);
    }

    /**
     * Reports whether a relation kind says what a symbol declares.
     *
     * @param string $kindValue The relation kind
     *
     * @return bool True when the analysed-output checks pin it instead
     */
    public static function isStructural(string $kindValue): bool
    {
        return in_array($kindValue, self::STRUCTURAL_EDGE_KINDS, true);
    }
}
