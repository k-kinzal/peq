<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;

/**
 * @internal
 *
 * Cross-validates peq's Graph output against PHP's Reflection API.
 *
 * Reflection API is an independent oracle: it derives structural facts about
 * PHP code via the PHP engine itself, completely independently from peq's
 * PHPStan-based static analysis. If both agree, we have high confidence that
 * peq's analysis is correct.
 *
 * Verifiable EdgeKinds (11 of 20 forward kinds):
 *   Declaration: Extends, Implements, TraitUse, Method, Property, Constant, EnumCase
 *   Type:        TypeParameter, TypeReturn, TypeProperty
 *   Other:       Attribute
 *
 * Usage edges (MethodCall, FunctionCall, etc.) are NOT verifiable by Reflection
 * because Reflection cannot inspect method body expressions.
 */
trait ReflectionCrossValidator
{
    // ──────────────────────────────────────────────
    //  Public assertion entry points
    // ──────────────────────────────────────────────

    /**
     * Soundness: every verifiable edge peq found is confirmed by Reflection.
     */
    private static function assertSoundness(Graph $graph): void
    {
        $result = self::checkSoundness($graph);

        self::assertGreaterThan(0, $result['verified'], 'Expected at least one verifiable edge');
        self::assertEmpty(
            $result['failures'],
            sprintf(
                "Soundness violations (%d verified, %d skipped, %d failed):\n%s",
                $result['verified'],
                $result['skipped'],
                count($result['failures']),
                implode("\n", array_slice($result['failures'], 0, 30))
            )
        );
    }

    /**
     * Completeness: every relationship Reflection finds has a corresponding edge in the Graph.
     */
    private static function assertCompleteness(Graph $graph): void
    {
        $result = self::checkCompleteness($graph);

        self::assertGreaterThan(0, $result['verified'], 'Expected at least one verifiable relationship');
        self::assertEmpty(
            $result['failures'],
            sprintf(
                "Completeness violations (%d verified, %d skipped, %d missing):\n%s",
                $result['verified'],
                $result['skipped'],
                count($result['failures']),
                implode("\n", array_slice($result['failures'], 0, 30))
            )
        );
    }

    /**
     * Soundness check filtered to a single EdgeKind.
     */
    private static function assertEdgeKindSoundness(Graph $graph, EdgeKind $kind): void
    {
        $result = self::checkSoundness($graph, $kind);

        self::assertGreaterThan(0, $result['verified'], "Expected at least one {$kind->value} edge to verify");
        self::assertEmpty(
            $result['failures'],
            sprintf(
                "%s soundness violations (%d verified, %d skipped, %d failed):\n%s",
                $kind->value,
                $result['verified'],
                $result['skipped'],
                count($result['failures']),
                implode("\n", $result['failures'])
            )
        );
    }

    /**
     * Completeness check filtered to a single EdgeKind.
     */
    private static function assertEdgeKindCompleteness(Graph $graph, EdgeKind $kind): void
    {
        $result = self::checkCompleteness($graph, $kind);

        self::assertGreaterThan(0, $result['verified'], "Expected at least one {$kind->value} relationship to verify");
        self::assertEmpty(
            $result['failures'],
            sprintf(
                "%s completeness violations (%d verified, %d skipped, %d missing):\n%s",
                $kind->value,
                $result['verified'],
                $result['skipped'],
                count($result['failures']),
                implode("\n", $result['failures'])
            )
        );
    }

    // ──────────────────────────────────────────────
    //  Soundness engine
    // ──────────────────────────────────────────────

    /**
     * @return array{verified: int, skipped: int, failures: list<string>}
     */
    private static function checkSoundness(Graph $graph, ?EdgeKind $filterKind = null): array
    {
        $verified = 0;
        $skipped = 0;
        $failures = [];

        foreach ($graph->nodes() as $node) {
            foreach ($graph->edges($node->id()) as $edge) {
                if (!self::isVerifiableEdgeKind($edge->kind())) {
                    continue;
                }
                if ($filterKind !== null && $edge->kind() !== $filterKind) {
                    continue;
                }

                $result = self::verifySoundEdge($edge);
                if ($result === true) {
                    ++$verified;
                } elseif ($result === null) {
                    ++$skipped;
                } else {
                    $failures[] = $result;
                }
            }
        }

        return ['verified' => $verified, 'skipped' => $skipped, 'failures' => $failures];
    }

    /**
     * @return null|string|true true=verified, null=skipped, string=failure message
     */
    private static function verifySoundEdge(Edge $edge): string|true|null
    {
        $fromFqn = $edge->from()->toString();
        $toFqn = $edge->to()->toString();

        return match ($edge->kind()) {
            EdgeKind::DeclarationExtends => self::verifySoundExtends($fromFqn, $toFqn),
            EdgeKind::DeclarationImplements => self::verifySoundImplements($fromFqn, $toFqn),
            EdgeKind::DeclarationTraitUse => self::verifySoundTraitUse($fromFqn, $toFqn),
            EdgeKind::DeclarationMethod => self::verifySoundMember($fromFqn, $toFqn, 'method'),
            EdgeKind::DeclarationProperty => self::verifySoundMember($fromFqn, $toFqn, 'property'),
            EdgeKind::DeclarationConstant => self::verifySoundMember($fromFqn, $toFqn, 'constant'),
            EdgeKind::DeclarationEnumCase => self::verifySoundEnumCase($fromFqn, $toFqn),
            EdgeKind::Attribute => self::verifySoundAttribute($fromFqn, $toFqn),
            EdgeKind::DeclarationTypeParameter => self::verifySoundTypeParameter($fromFqn, $toFqn),
            EdgeKind::DeclarationTypeReturn => self::verifySoundTypeReturn($fromFqn, $toFqn),
            EdgeKind::DeclarationTypeProperty => self::verifySoundTypeProperty($fromFqn, $toFqn),
            default => null,
        };
    }

    private static function verifySoundExtends(string $fromFqn, string $toFqn): string|true|null
    {
        $refl = self::safeReflectClass($fromFqn);
        if ($refl === null) {
            return null;
        }

        $parent = $refl->getParentClass();
        if ($parent === false) {
            return "SOUND: {$fromFqn} -[extends]-> {$toFqn} but Reflection says no parent";
        }
        if ($parent->getName() !== $toFqn) {
            return "SOUND: {$fromFqn} -[extends]-> {$toFqn} but Reflection says parent is {$parent->getName()}";
        }

        return true;
    }

    private static function verifySoundImplements(string $fromFqn, string $toFqn): string|true|null
    {
        $refl = self::safeReflectClass($fromFqn);
        if ($refl === null) {
            return null;
        }

        $directInterfaces = self::getDirectInterfaces($refl);
        if (!in_array($toFqn, $directInterfaces, true)) {
            return "SOUND: {$fromFqn} -[implements]-> {$toFqn} but not in direct interfaces: [".implode(', ', $directInterfaces).']';
        }

        return true;
    }

    private static function verifySoundTraitUse(string $fromFqn, string $toFqn): string|true|null
    {
        $refl = self::safeReflectClass($fromFqn);
        if ($refl === null) {
            return null;
        }

        if (!in_array($toFqn, $refl->getTraitNames(), true)) {
            return "SOUND: {$fromFqn} -[trait-use]-> {$toFqn} but not in traits: [".implode(', ', $refl->getTraitNames()).']';
        }

        return true;
    }

    /**
     * @param 'constant'|'method'|'property' $memberType
     */
    private static function verifySoundMember(string $fromFqn, string $toFqn, string $memberType): string|true|null
    {
        $classFqn = self::parseClassFqn($fromFqn);
        $memberName = self::parseMember($toFqn);
        if ($memberName === null) {
            return null;
        }

        $refl = self::safeReflectClass($classFqn);
        if ($refl === null) {
            return null;
        }

        $exists = match ($memberType) {
            'method' => $refl->hasMethod($memberName),
            'property' => $refl->hasProperty($memberName),
            'constant' => $refl->hasConstant($memberName),
        };

        if (!$exists) {
            return "SOUND: {$fromFqn} -[declaration-{$memberType}]-> {$toFqn} but {$memberType} '{$memberName}' not found on {$classFqn}";
        }

        return true;
    }

    private static function verifySoundEnumCase(string $fromFqn, string $toFqn): string|true|null
    {
        $classFqn = self::parseClassFqn($fromFqn);
        $caseName = self::parseMember($toFqn);
        if ($caseName === null) {
            return null;
        }

        $classRefl = self::safeReflectClass($classFqn);
        if ($classRefl === null || !$classRefl->isEnum()) {
            return null;
        }

        /** @var class-string<\UnitEnum> $enumClass */
        $enumClass = $classRefl->getName();
        $refl = new \ReflectionEnum($enumClass);

        if (!$refl->hasCase($caseName)) {
            return "SOUND: {$fromFqn} -[declaration-enum-case]-> {$toFqn} but case '{$caseName}' not found";
        }

        return true;
    }

    private static function verifySoundAttribute(string $fromFqn, string $toFqn): string|true|null
    {
        $attributes = self::getAttributeNames($fromFqn);
        if ($attributes === null) {
            return null;
        }

        if (!in_array($toFqn, $attributes, true)) {
            return "SOUND: {$fromFqn} -[attribute]-> {$toFqn} but not in attributes: [".implode(', ', $attributes).']';
        }

        return true;
    }

    private static function verifySoundTypeParameter(string $fromFqn, string $toFqn): string|true|null
    {
        $reflMethod = self::safeReflectMethod($fromFqn);
        if ($reflMethod === null) {
            return null;
        }

        $allTypes = [];
        foreach ($reflMethod->getParameters() as $param) {
            $allTypes = array_merge($allTypes, self::resolveReflectionTypes($param->getType()));
        }

        if (!in_array($toFqn, $allTypes, true)) {
            return "SOUND: {$fromFqn} -[type-parameter]-> {$toFqn} but not in parameter types: [".implode(', ', $allTypes).']';
        }

        return true;
    }

    private static function verifySoundTypeReturn(string $fromFqn, string $toFqn): string|true|null
    {
        $reflMethod = self::safeReflectMethod($fromFqn);
        if ($reflMethod === null) {
            return null;
        }

        $types = self::resolveReflectionTypes($reflMethod->getReturnType());
        if (!in_array($toFqn, $types, true)) {
            return "SOUND: {$fromFqn} -[type-return]-> {$toFqn} but not in return types: [".implode(', ', $types).']';
        }

        return true;
    }

    private static function verifySoundTypeProperty(string $fromFqn, string $toFqn): string|true|null
    {
        $memberName = self::parseMember($fromFqn);
        $classFqn = self::parseClassFqn($fromFqn);
        if ($memberName === null) {
            return null;
        }

        $refl = self::safeReflectClass($classFqn);
        if ($refl === null || !$refl->hasProperty($memberName)) {
            return null;
        }

        $types = self::resolveReflectionTypes($refl->getProperty($memberName)->getType());
        if (!in_array($toFqn, $types, true)) {
            return "SOUND: {$fromFqn} -[type-property]-> {$toFqn} but not in property types: [".implode(', ', $types).']';
        }

        return true;
    }

    // ──────────────────────────────────────────────
    //  Known divergences
    //
    //  Central registry of all known divergences between peq and Reflection.
    //  When adding or removing divergences, this method is the ONLY place to change.
    // ──────────────────────────────────────────────

    /**
     * Determines whether an expected edge is a known divergence that peq intentionally does not model.
     *
     * During completeness checks, Reflection may report relationships that peq's
     * graph does not contain. This method returns a reason string for known
     * divergences (which are skipped), or null if the edge should be verified.
     *
     * @param \ReflectionClass<object> $context Reflection of the class-like entity being checked
     * @param string                   $from    Source FQN of the expected edge
     * @param EdgeKind                 $kind    Edge kind
     * @param string                   $to      Target FQN of the expected edge
     *
     * @return null|string null = should be verified, string = skip reason
     */
    private static function knownDivergence(
        \ReflectionClass $context,
        string $from,
        EdgeKind $kind,
        string $to,
    ): ?string {
        // ── Category 1: Trait members ──
        // PHPStan processes trait methods/properties in the context of each using
        // class. $scope->getClassReflection() returns the using class, so declaration
        // edges are emitted from the using class, not from the trait node itself.
        // This is an architectural consequence of PHPStan and mirrors PHP's runtime
        // behavior where trait members are copied into each using class.
        if ($context->isTrait() && in_array($kind, [
            EdgeKind::DeclarationMethod,
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeProperty,
            EdgeKind::Attribute,
        ], true)) {
            return 'trait-members: PHPStan inlines trait members into using classes';
        }

        // ── Category 2: Enum built-in members ──
        // The PHP engine auto-generates cases(), from(), tryFrom() methods and
        // name, value properties for enums. These have no source-code declaration,
        // so peq's static analysis does not detect them.
        if ($context->isEnum()) {
            $member = self::parseMember($to);
            if ($kind === EdgeKind::DeclarationMethod
                && in_array($member, ['cases', 'from', 'tryFrom'], true)
            ) {
                return 'enum-builtins: auto-generated enum method';
            }
            if ($kind === EdgeKind::DeclarationProperty
                && in_array($member, ['name', 'value'], true)
            ) {
                return 'enum-builtins: auto-generated enum property';
            }
        }

        // ── Category 3: self/static/parent type references ──
        // peq treats self/static/parent as builtins via SourceResolver::isBuiltin(),
        // on par with int/string, and does not emit type edges for them.
        // - self:   self-referential; always satisfied, irrelevant for blast radius
        // - static: late static binding; does not refer to a concrete class
        // - parent: already captured by DeclarationExtends edges
        //
        // Reflection resolves 'self' to the concrete class name (getName() returns
        // e.g. "App\Config\Config"), while 'static' remains the literal "static".
        // Both are intentional exclusions in peq.
        if (in_array($kind, [
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeProperty,
        ], true)) {
            // 'self' resolved to the declaring class name by Reflection
            if ($to === $context->getName()) {
                return 'self-type: self resolves to declaring class (self-referential)';
            }
            // 'static'/'parent' remain as literal type names
            if (in_array(strtolower($to), ['static', 'parent'], true)) {
                return 'self-type: static/parent is a language construct, not a class dependency';
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────
    //  Completeness engine
    // ──────────────────────────────────────────────

    /**
     * @return array{verified: int, skipped: int, failures: list<string>}
     */
    private static function checkCompleteness(Graph $graph, ?EdgeKind $filterKind = null): array
    {
        $edgeIndex = self::buildEdgeIndex($graph);
        $verified = 0;
        $skipped = 0;
        $failures = [];

        foreach ($graph->nodes() as $node) {
            if (!in_array($node->kind(), [NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum], true)) {
                continue;
            }

            $fqn = $node->id()->toString();
            $refl = self::safeReflectClass($fqn);
            if ($refl === null) {
                ++$skipped;

                continue;
            }

            $checks = self::expectedEdgesFromReflection($refl, $fqn);
            foreach ($checks as [$fromFqn, $kind, $toFqn]) {
                if ($filterKind !== null && $kind !== $filterKind) {
                    continue;
                }

                if (self::edgeIndexHas($edgeIndex, $fromFqn, $kind, $toFqn)) {
                    ++$verified;
                } elseif (self::knownDivergence($refl, $fromFqn, $kind, $toFqn) !== null) {
                    ++$skipped;
                } else {
                    $failures[] = "COMPLETE: missing {$fromFqn} -[{$kind->value}]-> {$toFqn}";
                }
            }
        }

        return ['verified' => $verified, 'skipped' => $skipped, 'failures' => $failures];
    }

    /**
     * Derives ALL expected edges from Reflection for a single class-like entity.
     *
     * This method is intentionally filter-free. All filtering of known divergences
     * is handled centrally by knownDivergence().
     *
     * @param \ReflectionClass<object> $refl
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}>
     */
    private static function expectedEdgesFromReflection(\ReflectionClass $refl, string $fqn): array
    {
        $expected = [];

        // Extends
        $parent = $refl->getParentClass();
        if ($parent !== false) {
            $expected[] = [$fqn, EdgeKind::DeclarationExtends, $parent->getName()];
        }

        // Implements (direct only)
        foreach (self::getDirectInterfaces($refl) as $ifName) {
            $expected[] = [$fqn, EdgeKind::DeclarationImplements, $ifName];
        }

        // Trait use
        foreach ($refl->getTraitNames() as $traitName) {
            $expected[] = [$fqn, EdgeKind::DeclarationTraitUse, $traitName];
        }

        // Methods declared in this class
        foreach ($refl->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $refl->getName()) {
                continue;
            }
            $methodFqn = $fqn.'::'.$method->getName();
            $expected[] = [$fqn, EdgeKind::DeclarationMethod, $methodFqn];

            // Type edges for this method's parameters
            foreach ($method->getParameters() as $param) {
                foreach (self::resolveReflectionTypes($param->getType()) as $typeFqn) {
                    $expected[] = [$methodFqn, EdgeKind::DeclarationTypeParameter, $typeFqn];
                }
            }

            // Type edge for return type
            foreach (self::resolveReflectionTypes($method->getReturnType()) as $typeFqn) {
                $expected[] = [$methodFqn, EdgeKind::DeclarationTypeReturn, $typeFqn];
            }

            // Attributes on method
            foreach ($method->getAttributes() as $attr) {
                $expected[] = [$methodFqn, EdgeKind::Attribute, $attr->getName()];
            }
        }

        // Properties declared in this class
        foreach ($refl->getProperties() as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $refl->getName()) {
                continue;
            }
            $propFqn = $fqn.'::'.$prop->getName();
            $expected[] = [$fqn, EdgeKind::DeclarationProperty, $propFqn];

            // Type edge for property type
            foreach (self::resolveReflectionTypes($prop->getType()) as $typeFqn) {
                $expected[] = [$propFqn, EdgeKind::DeclarationTypeProperty, $typeFqn];
            }

            // Attributes on property
            foreach ($prop->getAttributes() as $attr) {
                $expected[] = [$propFqn, EdgeKind::Attribute, $attr->getName()];
            }
        }

        // Constants declared in this class
        foreach ($refl->getReflectionConstants() as $const) {
            if ($const->getDeclaringClass()->getName() !== $refl->getName()) {
                continue;
            }
            $constFqn = $fqn.'::'.$const->getName();

            // Enum cases vs regular constants
            if ($refl->isEnum()) {
                try {
                    /** @var class-string<\UnitEnum> $enumClassName */
                    $enumClassName = $refl->getName();
                    $enumRefl = new \ReflectionEnum($enumClassName);
                    if ($enumRefl->hasCase($const->getName())) {
                        $expected[] = [$fqn, EdgeKind::DeclarationEnumCase, $constFqn];

                        continue;
                    }
                } catch (\ReflectionException) {
                    // fallthrough to regular constant
                }
            }
            $expected[] = [$fqn, EdgeKind::DeclarationConstant, $constFqn];

            // Attributes on constant
            foreach ($const->getAttributes() as $attr) {
                $expected[] = [$constFqn, EdgeKind::Attribute, $attr->getName()];
            }
        }

        // Attributes on class itself
        foreach ($refl->getAttributes() as $attr) {
            $expected[] = [$fqn, EdgeKind::Attribute, $attr->getName()];
        }

        return $expected;
    }

    // ──────────────────────────────────────────────
    //  Reflection helpers
    // ──────────────────────────────────────────────

    /**
     * @return null|\ReflectionClass<object>
     */
    private static function safeReflectClass(string $fqn): ?\ReflectionClass
    {
        if (str_contains($fqn, '::')) {
            $fqn = self::parseClassFqn($fqn);
        }

        if (!class_exists($fqn) && !interface_exists($fqn) && !trait_exists($fqn) && !enum_exists($fqn)) {
            return null;
        }

        /** @var class-string $fqn */
        return new \ReflectionClass($fqn);
    }

    private static function safeReflectMethod(string $fqn): ?\ReflectionMethod
    {
        $classFqn = self::parseClassFqn($fqn);
        $methodName = self::parseMember($fqn);
        if ($methodName === null) {
            return null;
        }

        try {
            return new \ReflectionMethod($classFqn, $methodName);
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * Returns attribute FQNs for a given entity (class, method, property, constant).
     *
     * @return null|list<string>
     */
    private static function getAttributeNames(string $fqn): ?array
    {
        $memberName = self::parseMember($fqn);

        if ($memberName === null) {
            $refl = self::safeReflectClass($fqn);

            return $refl !== null
                ? array_map(fn (\ReflectionAttribute $a) => $a->getName(), $refl->getAttributes())
                : null;
        }

        $classFqn = self::parseClassFqn($fqn);
        $refl = self::safeReflectClass($classFqn);
        if ($refl === null) {
            return null;
        }

        if ($refl->hasMethod($memberName)) {
            return array_map(fn (\ReflectionAttribute $a) => $a->getName(), $refl->getMethod($memberName)->getAttributes());
        }
        if ($refl->hasProperty($memberName)) {
            return array_map(fn (\ReflectionAttribute $a) => $a->getName(), $refl->getProperty($memberName)->getAttributes());
        }
        if ($refl->hasConstant($memberName)) {
            $rc = $refl->getReflectionConstant($memberName);

            return $rc !== false
                ? array_map(fn (\ReflectionAttribute $a) => $a->getName(), $rc->getAttributes())
                : null;
        }

        return null;
    }

    /**
     * Computes directly implemented interfaces, excluding inherited ones.
     *
     * @param \ReflectionClass<object> $class
     *
     * @return list<string>
     */
    private static function getDirectInterfaces(\ReflectionClass $class): array
    {
        $all = $class->getInterfaceNames();
        $inherited = [];

        $parent = $class->getParentClass();
        if ($parent !== false) {
            $inherited = array_merge($inherited, $parent->getInterfaceNames());
        }

        foreach ($class->getInterfaces() as $iface) {
            foreach ($iface->getInterfaceNames() as $parentIface) {
                $inherited[] = $parentIface;
            }
        }

        foreach ($class->getTraits() as $trait) {
            $inherited = array_merge($inherited, $trait->getInterfaceNames());
        }

        if ($class->isEnum()) {
            $inherited[] = \UnitEnum::class;
            $inherited[] = \BackedEnum::class;
        }

        if ($class->hasMethod('__toString')) {
            $inherited[] = \Stringable::class;
        }

        return array_values(array_diff($all, array_unique($inherited)));
    }

    /**
     * Recursively resolves a ReflectionType to non-builtin FQN strings.
     *
     * This method only filters PHP engine builtins (int, string, etc.).
     * Language constructs like self/static/parent are NOT filtered here;
     * they are handled centrally by knownDivergence().
     *
     * @return list<string>
     */
    private static function resolveReflectionTypes(?\ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof \ReflectionNamedType) {
            return $type->isBuiltin() ? [] : [$type->getName()];
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            $names = [];
            foreach ($type->getTypes() as $subType) {
                $names = array_merge($names, self::resolveReflectionTypes($subType));
            }

            return $names;
        }

        return [];
    }

    // ──────────────────────────────────────────────
    //  FQN parsing helpers
    // ──────────────────────────────────────────────

    /**
     * Extracts class FQN from a node ID string.
     * "Ns\Class::member" -> "Ns\Class", "Ns\Class" -> "Ns\Class".
     */
    private static function parseClassFqn(string $nodeId): string
    {
        $pos = strpos($nodeId, '::');

        return $pos !== false ? substr($nodeId, 0, $pos) : $nodeId;
    }

    /**
     * Extracts member name from a node ID string.
     * "Ns\Class::member" -> "member", "Ns\Class" -> null.
     */
    private static function parseMember(string $nodeId): ?string
    {
        $pos = strpos($nodeId, '::');

        return $pos !== false ? substr($nodeId, $pos + 2) : null;
    }

    /**
     * @return array<string, true>
     */
    private static function buildEdgeIndex(Graph $graph): array
    {
        $index = [];
        foreach ($graph->nodes() as $node) {
            foreach ($graph->edges($node->id()) as $edge) {
                $key = $edge->from()->toString().'|'.$edge->kind()->value.'|'.$edge->to()->toString();
                $index[$key] = true;
            }
        }

        return $index;
    }

    /**
     * @param array<string, true> $index
     */
    private static function edgeIndexHas(array $index, string $from, EdgeKind $kind, string $to): bool
    {
        return isset($index[$from.'|'.$kind->value.'|'.$to]);
    }

    private static function isVerifiableEdgeKind(EdgeKind $kind): bool
    {
        return in_array($kind, [
            EdgeKind::DeclarationExtends,
            EdgeKind::DeclarationImplements,
            EdgeKind::DeclarationTraitUse,
            EdgeKind::DeclarationMethod,
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationConstant,
            EdgeKind::DeclarationEnumCase,
            EdgeKind::Attribute,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeProperty,
        ], true);
    }
}
