<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Structural bridge between GrammarCoverageContractTest (proof obligation 3)
 * and DeclarationEdgeContractTest + UsageEdgeContractTest (proof obligation 4).
 *
 * Verifies that every DEPENDENCY_PRODUCING PhpParser node type has:
 * - An explicit mapping to the EdgeKind(s) it should produce
 * - A corresponding contract test covering each mapped EdgeKind, OR
 * - An entry in the known-gap list with justification
 */
final class EdgeCoverageContractTest extends TestCase
{
    /**
     * Maps each DEPENDENCY_PRODUCING PhpParser node type to the EdgeKind(s) it produces.
     *
     * Every key in GrammarCoverageContractTest::DEPENDENCY_PRODUCING must appear here.
     * Type nodes (UnionType, IntersectionType, NullableType) are helpers that participate
     * in type-producing edges; they map to the type-declaration edge kinds they contribute to.
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
     * EdgeKinds that are covered by existing contract tests.
     *
     * @var array<string, class-string<TestCase>>
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
     * EdgeKinds that appear in NODE_TO_EDGE_KINDS but have no contract test yet.
     * Currently empty — all edge kinds have processors and contract tests.
     *
     * @var array<string, string>
     */
    private const KNOWN_GAPS = [
        // All edge kinds now have processors and contract tests.
    ];

    /**
     * EdgeKinds that have processors but with known scope limitations.
     * Unlike KNOWN_GAPS (no processor), these have working processors
     * that only handle a subset of the PHP grammar for that edge kind.
     *
     * @var array<string, string>
     */
    private const RECEIVER_LIMITATIONS = [
        'method-call' => 'Only $this->method() — $obj->method() requires PHPStan type inference',
        'property-access' => 'Only $this->prop — $obj->prop requires PHPStan type inference',
    ];

    /**
     * Structural edge kinds representing parent-child declaration relationships.
     * These are inherently tested by any contract test that declares a class with members.
     *
     * @var list<string>
     */
    private const STRUCTURAL_EDGE_KINDS = [
        'declaration-method',
        'declaration-property',
        'declaration-constant',
        'declaration-enum-case',
    ];

    #[Test]
    public function testEveryDependencyProducingNodeHasEdgeKindMapping(): void
    {
        /** @var array<class-string, string> $raw */
        $raw = (new \ReflectionClassConstant(
            GrammarCoverageContractTest::class,
            'DEPENDENCY_PRODUCING',
        ))->getValue();
        $dependencyProducing = array_keys($raw);

        $mapped = array_keys(self::NODE_TO_EDGE_KINDS);

        $unmapped = array_diff($dependencyProducing, $mapped);
        self::assertSame(
            [],
            array_values($unmapped),
            'DEPENDENCY_PRODUCING entries without EdgeKind mapping: '
            .implode(', ', $unmapped),
        );

        $extra = array_diff($mapped, $dependencyProducing);
        self::assertSame(
            [],
            array_values($extra),
            'NODE_TO_EDGE_KINDS entries not in DEPENDENCY_PRODUCING: '
            .implode(', ', $extra),
        );
    }

    #[Test]
    public function testEveryMappedEdgeKindIsCoveredOrGapped(): void
    {
        $allMappedKinds = [];
        foreach (self::NODE_TO_EDGE_KINDS as $edgeKinds) {
            foreach ($edgeKinds as $kind) {
                $allMappedKinds[$kind->value] = true;
            }
        }

        /** @var array<string, string> $knownGaps */
        $knownGaps = self::KNOWN_GAPS;
        foreach (array_keys($allMappedKinds) as $kindValue) {
            $isTested = isset(self::TESTED_EDGE_KINDS[$kindValue]);
            $isGap = isset($knownGaps[$kindValue]);
            $isStructural = in_array($kindValue, self::STRUCTURAL_EDGE_KINDS, true);

            self::assertTrue(
                $isTested || $isGap || $isStructural,
                sprintf(
                    'EdgeKind "%s" is mapped from a DEPENDENCY_PRODUCING node but is '
                    .'not in TESTED_EDGE_KINDS, KNOWN_GAPS, or STRUCTURAL_EDGE_KINDS',
                    $kindValue,
                ),
            );
        }
    }

    #[Test]
    public function testNoStaleGaps(): void
    {
        /** @var array<string, string> $knownGaps */
        $knownGaps = self::KNOWN_GAPS;
        $stale = array_intersect_key($knownGaps, self::TESTED_EDGE_KINDS);

        self::assertSame(
            [],
            array_keys($stale),
            'The following KNOWN_GAPS entries now have contract tests and should be removed: '
            .implode(', ', array_keys($stale)),
        );
    }

    #[Test]
    public function testTestedEdgeKindClassesExist(): void
    {
        foreach (self::TESTED_EDGE_KINDS as $kindValue => $testClass) {
            self::assertTrue(
                class_exists($testClass),
                sprintf('TESTED_EDGE_KINDS["%s"] references non-existent class: %s', $kindValue, $testClass),
            );

            $rc = new \ReflectionClass($testClass);
            self::assertTrue(
                $rc->isSubclassOf(TestCase::class),
                sprintf('TESTED_EDGE_KINDS["%s"] references %s which is not a TestCase', $kindValue, $testClass),
            );
        }
    }

    #[Test]
    public function testNodeToEdgeKindsOnlyContainsValidEdgeKinds(): void
    {
        $inverseKinds = [EdgeKind::UsedBy, EdgeKind::DeclaredIn];

        foreach (self::NODE_TO_EDGE_KINDS as $nodeClass => $edgeKinds) {
            foreach ($edgeKinds as $kind) {
                self::assertNotContains(
                    $kind,
                    $inverseKinds,
                    sprintf(
                        'NODE_TO_EDGE_KINDS["%s"] contains inverse edge %s',
                        $nodeClass,
                        $kind->name,
                    ),
                );
            }
        }
    }

    #[Test]
    public function testEveryForwardEdgeKindIsReachableFromSomeNode(): void
    {
        $inverseKinds = [EdgeKind::UsedBy, EdgeKind::DeclaredIn];

        $reachable = [];
        foreach (self::NODE_TO_EDGE_KINDS as $edgeKinds) {
            foreach ($edgeKinds as $kind) {
                $reachable[$kind->value] = true;
            }
        }

        foreach (EdgeKind::cases() as $case) {
            if (in_array($case, $inverseKinds, true)) {
                continue;
            }
            self::assertArrayHasKey(
                $case->value,
                $reachable,
                sprintf(
                    'EdgeKind::%s is not reachable from any DEPENDENCY_PRODUCING node in NODE_TO_EDGE_KINDS',
                    $case->name,
                ),
            );
        }
    }

    #[Test]
    public function testReceiverLimitationsReferToTestedEdgeKinds(): void
    {
        foreach (array_keys(self::RECEIVER_LIMITATIONS) as $kindValue) {
            self::assertArrayHasKey(
                $kindValue,
                self::TESTED_EDGE_KINDS,
                sprintf('RECEIVER_LIMITATIONS["%s"] is not in TESTED_EDGE_KINDS', $kindValue),
            );
        }
    }
}
