<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use BackedEnum;
use PHPUnit\Framework\Assert;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;
use UnitEnum;

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
final class ReflectionCrossCheck
{
    /**
     * Soundness: every verifiable edge peq found is confirmed by Reflection.
     */
    public static function assertSoundness(Graph $graph): void
    {
        $result = self::checkSoundness($graph);

        Assert::assertGreaterThan(0, $result['verified'], 'Expected at least one verifiable edge');
        Assert::assertEmpty(
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
    public static function assertCompleteness(Graph $graph): void
    {
        $result = self::checkCompleteness($graph);

        Assert::assertGreaterThan(0, $result['verified'], 'Expected at least one verifiable relationship');
        Assert::assertEmpty(
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
    public static function assertEdgeKindSoundness(Graph $graph, EdgeKind $kind): void
    {
        $result = self::checkSoundness($graph, $kind);

        Assert::assertGreaterThan(0, $result['verified'], "Expected at least one {$kind->value} edge to verify");
        Assert::assertEmpty(
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
    public static function assertEdgeKindCompleteness(Graph $graph, EdgeKind $kind): void
    {
        $result = self::checkCompleteness($graph, $kind);

        Assert::assertGreaterThan(0, $result['verified'], "Expected at least one {$kind->value} relationship to verify");
        Assert::assertEmpty(
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

    /**
     * @return array{verified: int, skipped: int, failures: list<string>}
     */
    public static function checkSoundness(Graph $graph, ?EdgeKind $filterKind = null): array
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
                } elseif (is_string($result)) {
                    $failures[] = $result;
                }
            }
        }

        return ['verified' => $verified, 'skipped' => $skipped, 'failures' => $failures];
    }

    /**
     * Checks one relation peq recorded against what Reflection reports.
     *
     * Every kind of relation is named here, including the ones Reflection cannot
     * confirm: what a method body reaches is not visible to it, and saying so arm by
     * arm is what makes a relation kind added to the graph model turn up here rather
     * than quietly reading as unverifiable.
     *
     * @param Edge $edge The relation peq recorded
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundEdge(Edge $edge): bool|string|null
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
            EdgeKind::FunctionCall,
            EdgeKind::MethodCall,
            EdgeKind::StaticCall,
            EdgeKind::Instantiation,
            EdgeKind::PropertyAccess,
            EdgeKind::StaticPropertyAccess,
            EdgeKind::ConstFetch,
            EdgeKind::Instanceof,
            EdgeKind::Catch,
            EdgeKind::UsedBy,
            EdgeKind::DeclaredIn => null,
        };
    }

    /**
     * Checks a relation peq recorded as inheritance against Reflection.
     *
     * An interface extending an interface is written `extends`, and that is the kind
     * peq records; Reflection has no parent for it and reports the same relation
     * through getInterfaceNames(), so the oracle asks it there.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundExtends(string $fromFqn, string $toFqn): bool|string|null
    {
        $refl = self::safeReflectClass($fromFqn);
        if ($refl === null) {
            return null;
        }

        if ($refl->isInterface()) {
            $parents = self::getDirectInterfaces($refl);

            return in_array($toFqn, $parents, true)
                ? true
                : "SOUND: {$fromFqn} -[extends]-> {$toFqn} but not in extended interfaces: [".implode(', ', $parents).']';
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

    /**
     * Checks a relation peq recorded as an implemented interface.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundImplements(string $fromFqn, string $toFqn): bool|string|null
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

    /**
     * Checks a relation peq recorded as a trait being used.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundTraitUse(string $fromFqn, string $toFqn): bool|string|null
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
    public static function verifySoundMember(string $fromFqn, string $toFqn, string $memberType): bool|string|null
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

    /**
     * Checks a relation peq recorded as an enum case being declared.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundEnumCase(string $fromFqn, string $toFqn): bool|string|null
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

        /** @var class-string<UnitEnum> $enumClass */
        $enumClass = $classRefl->getName();

        try {
            $refl = new ReflectionEnum($enumClass);
        } catch (ReflectionException) {
            return null;
        }

        if (!$refl->hasCase($caseName)) {
            return "SOUND: {$fromFqn} -[declaration-enum-case]-> {$toFqn} but case '{$caseName}' not found";
        }

        return true;
    }

    /**
     * Checks a relation peq recorded as an attribute being written.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundAttribute(string $fromFqn, string $toFqn): bool|string|null
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

    /**
     * Checks a relation peq recorded as a parameter type.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundTypeParameter(string $fromFqn, string $toFqn): bool|string|null
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

    /**
     * Checks a relation peq recorded as a return type.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundTypeReturn(string $fromFqn, string $toFqn): bool|string|null
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

    /**
     * Checks a relation peq recorded as a property type.
     *
     * @param string $fromFqn The name the relation starts at
     * @param string $toFqn   The name the relation points at
     *
     * @return null|bool|string True when Reflection confirms it, a message when it
     *                          contradicts it, and null when Reflection cannot say
     */
    public static function verifySoundTypeProperty(string $fromFqn, string $toFqn): bool|string|null
    {
        $memberName = self::parseMember($fromFqn);
        $classFqn = self::parseClassFqn($fromFqn);
        if ($memberName === null) {
            return null;
        }

        $refl = self::safeReflectClass($classFqn);
        if ($refl === null) {
            return null;
        }

        try {
            $property = $refl->getProperty($memberName);
        } catch (ReflectionException) {
            return null;
        }

        $types = self::resolveReflectionTypes($property->getType());
        if (!in_array($toFqn, $types, true)) {
            return "SOUND: {$fromFqn} -[type-property]-> {$toFqn} but not in property types: [".implode(', ', $types).']';
        }

        return true;
    }

    /**
     * Reports why a relation Reflection sees is one peq is not expected to record.
     *
     * The completeness check asks Reflection what should exist and peq what does;
     * where the two models genuinely differ, the difference is named here and
     * nowhere else, so that a missing relation is either a known difference or a
     * defect and never an open question.
     *
     * @param ReflectionClass<object> $context The symbol whose relations are checked
     * @param string                  $from    The name the relation starts at
     * @param EdgeKind                $kind    The kind of relation
     * @param string                  $to      The name the relation points at
     *
     * @return null|string The reason it is not expected, or null when peq must have it
     */
    public static function knownDivergence(
        ReflectionClass $context,
        string $from,
        EdgeKind $kind,
        string $to,
    ): ?string {
        return self::traitMemberDivergence($context, $kind)
            ?? self::enumBuiltinDivergence($context, $kind, $to)
            ?? self::relativeTypeDivergence($context, $kind, $to);
    }

    /**
     * Reports the members a trait declares, which peq records against its users.
     *
     * PHPStan analyses a trait's members in the context of each class that uses it,
     * so the scope a collector sees names the using class. peq therefore records the
     * declaration against the user rather than against the trait, which mirrors what
     * PHP itself does with trait members at runtime.
     *
     * @param ReflectionClass<object> $context The symbol whose relations are checked
     * @param EdgeKind                $kind    The kind of relation
     *
     * @return null|string The reason it is not expected, or null when peq must have it
     */
    public static function traitMemberDivergence(ReflectionClass $context, EdgeKind $kind): ?string
    {
        $inlined = [
            EdgeKind::DeclarationMethod,
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeProperty,
            EdgeKind::Attribute,
        ];

        return $context->isTrait() && in_array($kind, $inlined, true)
            ? 'trait-members: PHPStan inlines trait members into using classes'
            : null;
    }

    /**
     * Reports the members the engine generates for an enum, which no source declares.
     *
     * `cases()`, `from()` and `tryFrom()`, and the `name` and `value` properties, are
     * written by the PHP engine rather than by anyone's code, so static analysis has
     * nothing to read them from.
     *
     * @param ReflectionClass<object> $context The symbol whose relations are checked
     * @param EdgeKind                $kind    The kind of relation
     * @param string                  $to      The name the relation points at
     *
     * @return null|string The reason it is not expected, or null when peq must have it
     */
    public static function enumBuiltinDivergence(ReflectionClass $context, EdgeKind $kind, string $to): ?string
    {
        if (!$context->isEnum()) {
            return null;
        }

        $member = self::parseMember($to);
        if ($kind === EdgeKind::DeclarationMethod && in_array($member, ['cases', 'from', 'tryFrom'], true)) {
            return 'enum-builtins: auto-generated enum method';
        }

        return $kind === EdgeKind::DeclarationProperty && in_array($member, ['name', 'value'], true)
            ? 'enum-builtins: auto-generated enum property'
            : null;
    }

    /**
     * Reports the type positions naming `self`, `static` or `parent`.
     *
     * peq treats them as builtins, on a par with int and string, because none of them
     * names a dependency: `self` is the declaring symbol itself, `static` is decided
     * at the call site, and `parent` is already recorded as inheritance. Reflection
     * resolves `self` to the declaring class name and leaves the other two as written.
     *
     * @param ReflectionClass<object> $context The symbol whose relations are checked
     * @param EdgeKind                $kind    The kind of relation
     * @param string                  $to      The name the relation points at
     *
     * @return null|string The reason it is not expected, or null when peq must have it
     */
    public static function relativeTypeDivergence(ReflectionClass $context, EdgeKind $kind, string $to): ?string
    {
        $typePositions = [
            EdgeKind::DeclarationTypeReturn,
            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeProperty,
        ];
        if (!in_array($kind, $typePositions, true)) {
            return null;
        }

        if ($to === $context->getName() || strtolower($to) === 'self') {
            return 'self-type: self resolves to declaring class (self-referential)';
        }

        return in_array(strtolower($to), ['static', 'parent'], true)
            ? 'self-type: static/parent is a language construct, not a class dependency'
            : null;
    }

    /**
     * @return array{verified: int, skipped: int, failures: list<string>}
     */
    public static function checkCompleteness(Graph $graph, ?EdgeKind $filterKind = null): array
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
     * Derives every relation Reflection reports for one class-like symbol.
     *
     * The list is deliberately unfiltered: everything Reflection can see is stated
     * here, and knownDivergence() is the one place that decides what peq is not
     * expected to have recorded.
     *
     * @param ReflectionClass<object> $refl The reflection of the symbol
     * @param string                  $fqn  The name peq knows the symbol by
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}> The relations it reports
     */
    public static function expectedEdgesFromReflection(ReflectionClass $refl, string $fqn): array
    {
        return [
            ...self::expectedInheritance($refl, $fqn),
            ...self::expectedMethods($refl, $fqn),
            ...self::expectedProperties($refl, $fqn),
            ...self::expectedConstants($refl, $fqn),
            ...self::expectedAttributes($refl->getAttributes(), $fqn),
        ];
    }

    /**
     * Derives what a symbol inherits: its parent, its interfaces and its traits.
     *
     * An interface listing another interface is written `extends`, and that is the
     * kind peq records for it; only a class or an enum implements one.
     *
     * @param ReflectionClass<object> $refl The reflection of the symbol
     * @param string                  $fqn  The name peq knows the symbol by
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}> The inheritance relations
     */
    public static function expectedInheritance(ReflectionClass $refl, string $fqn): array
    {
        $expected = [];

        $parent = $refl->getParentClass();
        if ($parent !== false) {
            $expected[] = [$fqn, EdgeKind::DeclarationExtends, $parent->getName()];
        }

        foreach (self::getDirectInterfaces($refl) as $ifName) {
            $expected[] = [$fqn, $refl->isInterface() ? EdgeKind::DeclarationExtends : EdgeKind::DeclarationImplements, $ifName];
        }

        foreach ($refl->getTraitNames() as $traitName) {
            $expected[] = [$fqn, EdgeKind::DeclarationTraitUse, $traitName];
        }

        return $expected;
    }

    /**
     * Derives the methods a symbol declares, with their signatures and attributes.
     *
     * A method a symbol inherits is declared by whatever declared it, so only the
     * ones whose declaring class is this symbol are its own.
     *
     * @param ReflectionClass<object> $refl The reflection of the symbol
     * @param string                  $fqn  The name peq knows the symbol by
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}> The method relations
     */
    public static function expectedMethods(ReflectionClass $refl, string $fqn): array
    {
        $expected = [];
        foreach ($refl->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $refl->getName()) {
                continue;
            }

            $methodFqn = $fqn.'::'.$method->getName();
            $expected[] = [$fqn, EdgeKind::DeclarationMethod, $methodFqn];

            foreach ($method->getParameters() as $param) {
                foreach (self::resolveReflectionTypes($param->getType()) as $typeFqn) {
                    $expected[] = [$methodFqn, EdgeKind::DeclarationTypeParameter, $typeFqn];
                }
            }

            foreach (self::resolveReflectionTypes($method->getReturnType()) as $typeFqn) {
                $expected[] = [$methodFqn, EdgeKind::DeclarationTypeReturn, $typeFqn];
            }

            foreach (self::expectedAttributes($method->getAttributes(), $methodFqn) as $attribute) {
                $expected[] = $attribute;
            }
        }

        return $expected;
    }

    /**
     * Derives the properties a symbol declares, with their types and attributes.
     *
     * @param ReflectionClass<object> $refl The reflection of the symbol
     * @param string                  $fqn  The name peq knows the symbol by
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}> The property relations
     */
    public static function expectedProperties(ReflectionClass $refl, string $fqn): array
    {
        $expected = [];
        foreach ($refl->getProperties() as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $refl->getName()) {
                continue;
            }

            $propFqn = $fqn.'::'.$prop->getName();
            $expected[] = [$fqn, EdgeKind::DeclarationProperty, $propFqn];

            foreach (self::resolveReflectionTypes($prop->getType()) as $typeFqn) {
                $expected[] = [$propFqn, EdgeKind::DeclarationTypeProperty, $typeFqn];
            }

            foreach (self::expectedAttributes($prop->getAttributes(), $propFqn) as $attribute) {
                $expected[] = $attribute;
            }
        }

        return $expected;
    }

    /**
     * Derives the constants a symbol declares, telling enum cases from constants.
     *
     * The engine reports both through the same reflection, so an enum is asked
     * whether the name it declares is one of its cases.
     *
     * @param ReflectionClass<object> $refl The reflection of the symbol
     * @param string                  $fqn  The name peq knows the symbol by
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}> The constant relations
     */
    public static function expectedConstants(ReflectionClass $refl, string $fqn): array
    {
        $expected = [];
        foreach ($refl->getReflectionConstants() as $const) {
            if ($const->getDeclaringClass()->getName() !== $refl->getName()) {
                continue;
            }

            $constFqn = $fqn.'::'.$const->getName();
            $expected[] = [$fqn, self::isEnumCase($refl, $const->getName()) ? EdgeKind::DeclarationEnumCase : EdgeKind::DeclarationConstant, $constFqn];

            foreach (self::expectedAttributes($const->getAttributes(), $constFqn) as $attribute) {
                $expected[] = $attribute;
            }
        }

        return $expected;
    }

    /**
     * Reports whether a name a symbol declares is one of its enum cases.
     *
     * @param ReflectionClass<object> $refl The reflection of the symbol
     * @param string                  $name The name it declares
     *
     * @return bool True when the symbol is an enum and the name is one of its cases
     */
    public static function isEnumCase(ReflectionClass $refl, string $name): bool
    {
        if (!$refl->isEnum()) {
            return false;
        }

        try {
            /** @var class-string<UnitEnum> $enumClassName */
            $enumClassName = $refl->getName();

            return (new ReflectionEnum($enumClassName))->hasCase($name);
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Derives the attribute relations of one declaration.
     *
     * @param list<ReflectionAttribute<object>> $attributes The attributes written on it
     * @param string                            $fqn        The name peq knows the declaration by
     *
     * @return list<array{0: string, 1: EdgeKind, 2: string}> The attribute relations
     */
    public static function expectedAttributes(array $attributes, string $fqn): array
    {
        $expected = [];
        foreach ($attributes as $attribute) {
            $expected[] = [$fqn, EdgeKind::Attribute, $attribute->getName()];
        }

        return $expected;
    }

    /**
     * @return null|ReflectionClass<object>
     */
    public static function safeReflectClass(string $fqn): ?ReflectionClass
    {
        if (str_contains($fqn, '::')) {
            $fqn = self::parseClassFqn($fqn);
        }

        if (!class_exists($fqn) && !interface_exists($fqn) && !trait_exists($fqn) && !enum_exists($fqn)) {
            return null;
        }

        /** @var class-string $fqn */
        return new ReflectionClass($fqn);
    }

    /**
     * Reflects the method a name refers to, or reports that it cannot be reflected.
     *
     * A name peq recorded may belong to a symbol outside the analysed sources, which
     * nothing here can load; that is a question Reflection cannot answer rather than
     * a disagreement with peq.
     *
     * @param string $fqn The name peq knows the method by
     *
     * @return null|ReflectionMethod The reflection, or null when there is none to have
     */
    public static function safeReflectMethod(string $fqn): ?ReflectionMethod
    {
        $classFqn = self::parseClassFqn($fqn);
        $methodName = self::parseMember($fqn);
        if ($methodName === null) {
            return null;
        }

        try {
            return new ReflectionMethod($classFqn, $methodName);
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Returns attribute FQNs for a given entity (class, method, property, constant).
     *
     * @return null|list<string>
     */
    public static function getAttributeNames(string $fqn): ?array
    {
        $memberName = self::parseMember($fqn);

        if ($memberName === null) {
            $refl = self::safeReflectClass($fqn);

            return $refl !== null
                ? array_map(fn (ReflectionAttribute $a) => $a->getName(), $refl->getAttributes())
                : null;
        }

        $classFqn = self::parseClassFqn($fqn);
        $refl = self::safeReflectClass($classFqn);
        if ($refl === null) {
            return null;
        }

        if ($refl->hasMethod($memberName)) {
            try {
                $method = $refl->getMethod($memberName);
            } catch (ReflectionException) {
                return null;
            }
            $names = array_map(fn (ReflectionAttribute $a) => $a->getName(), $method->getAttributes());
            foreach ($method->getParameters() as $parameter) {
                foreach ($parameter->getAttributes() as $attribute) {
                    $names[] = $attribute->getName();
                }
            }

            return array_values(array_unique($names));
        }
        if ($refl->hasProperty($memberName)) {
            try {
                return array_map(fn (ReflectionAttribute $a) => $a->getName(), $refl->getProperty($memberName)->getAttributes());
            } catch (ReflectionException) {
                return null;
            }
        }
        if ($refl->hasConstant($memberName)) {
            $rc = $refl->getReflectionConstant($memberName);

            return $rc !== false
                ? array_map(fn (ReflectionAttribute $a) => $a->getName(), $rc->getAttributes())
                : null;
        }

        return null;
    }

    /**
     * Computes directly implemented interfaces, excluding inherited ones.
     *
     * @param ReflectionClass<object> $class
     *
     * @return list<string>
     */
    public static function getDirectInterfaces(ReflectionClass $class): array
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
            $inherited[] = UnitEnum::class;
            $inherited[] = BackedEnum::class;
        }

        if ($class->hasMethod('__toString')) {
            $inherited[] = Stringable::class;
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
    public static function resolveReflectionTypes(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? [] : [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];
            foreach ($type->getTypes() as $subType) {
                $names = array_merge($names, self::resolveReflectionTypes($subType));
            }

            return $names;
        }

        return [];
    }

    /**
     * Extracts class FQN from a node ID string.
     * "Ns\Class::member" -> "Ns\Class", "Ns\Class" -> "Ns\Class".
     */
    public static function parseClassFqn(string $nodeId): string
    {
        $pos = strpos($nodeId, '::');

        return $pos !== false ? substr($nodeId, 0, $pos) : $nodeId;
    }

    /**
     * Extracts member name from a node ID string.
     * "Ns\Class::member" -> "member", "Ns\Class" -> null.
     */
    public static function parseMember(string $nodeId): ?string
    {
        $pos = strpos($nodeId, '::');

        return $pos !== false ? substr($nodeId, $pos + 2) : null;
    }

    /**
     * @return array<string, true>
     */
    public static function buildEdgeIndex(Graph $graph): array
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
    public static function edgeIndexHas(array $index, string $from, EdgeKind $kind, string $to): bool
    {
        return isset($index[$from.'|'.$kind->value.'|'.$to]);
    }

    /**
     * Reports whether Reflection can say anything about a kind of relation.
     *
     * Reflection reads declarations, so it can confirm what a symbol declares and
     * inherits. What a method body reaches is invisible to it, and a relation of
     * that kind is neither confirmed nor contradicted here.
     *
     * @param EdgeKind $kind The kind of relation
     *
     * @return bool True when Reflection is an oracle for that kind
     */
    public static function isVerifiableEdgeKind(EdgeKind $kind): bool
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
