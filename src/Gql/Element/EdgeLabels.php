<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Analyzer\Graph\EdgeKind;

/**
 * The labels a relation carries when a query looks at it.
 *
 * The graph names its relations the way an analyser does — `declaration-extends`,
 * `method-call` — and a query language names them the way a sentence does. A pattern
 * reads `-[:extends]->` and `-[:methodCall]->`, which is what a reader writing about
 * code would say, and is also what the rest of the graph world spells its labels
 * like.
 *
 * Families matter here as much as they do for symbols. `-[:call]->` covers a call to
 * a function, to a method and to a static method in one pattern, which is what
 * "everything this reaches by calling" means; `-[:declaration]->` covers everything a
 * class-like writes down about itself.
 *
 * The particular name is a lookup and the families are a decision, which is why one
 * is written as data and the other as a match. Both are still total: a kind added
 * without a name is an offset static analysis cannot prove exists, and one added
 * without a family is an unhandled arm.
 *
 * @visibility App\Gql
 */
final class EdgeLabels
{
    /**
     * What a query calls each kind of relation.
     */
    private const array NAMES = [
        'function-call' => 'functionCall',
        'method-call' => 'methodCall',
        'possible-call' => 'possibleCall',
        'static-call' => 'staticCall',
        'instantiation' => 'instantiation',
        'property-access' => 'propertyAccess',
        'static-property-access' => 'staticPropertyAccess',
        'const-fetch' => 'constFetch',
        'instanceof' => 'instanceOf',
        'catch' => 'catches',
        'declaration-trait-use' => 'traitUse',
        'declaration-extends' => 'extends',
        'declaration-implements' => 'implements',
        'declaration-method' => 'declaresMethod',
        'declaration-property' => 'declaresProperty',
        'declaration-constant' => 'declaresConstant',
        'declaration-enum-case' => 'declaresEnumCase',
        'declaration-type-parameter' => 'parameterType',
        'declaration-type-return' => 'returnType',
        'declaration-type-property' => 'propertyType',
        'attribute' => 'attribute',
        'phpdoc' => 'phpDoc',
        'used-by' => 'usedBy',
        'declared-in' => 'declaredIn',
    ];

    /**
     * Returns the labels a kind of relation carries.
     *
     * @param EdgeKind $kind The kind of relation
     *
     * @example A call carries the family every call belongs to
     *     \App\Gql\Element\EdgeLabels::of(\App\Analyzer\Graph\EdgeKind::MethodCall) // => ['methodCall', 'call', 'usage']
     * @example A declaration says which one it is and that it is one
     *     \App\Gql\Element\EdgeLabels::of(\App\Analyzer\Graph\EdgeKind::DeclarationExtends) // => ['extends', 'declaration']
     *
     * @return list<string> The labels, most specific first
     */
    public static function of(EdgeKind $kind): array
    {
        return [self::name($kind), ...self::belongsTo($kind)];
    }

    /**
     * Returns what a query calls one kind of relation.
     *
     * @param EdgeKind $kind The kind of relation
     *
     * @example A relation is named the way a sentence about code would name it
     *     \App\Gql\Element\EdgeLabels::name(\App\Analyzer\Graph\EdgeKind::DeclarationMethod) // => 'declaresMethod'
     *
     * @return string The name
     */
    public static function name(EdgeKind $kind): string
    {
        return self::NAMES[$kind->value];
    }

    /**
     * Returns the families of relation a kind of relation belongs to.
     *
     * @param EdgeKind $kind The kind of relation
     *
     * @example Every call belongs to the family a pattern selects calls by
     *     \App\Gql\Element\EdgeLabels::belongsTo(\App\Analyzer\Graph\EdgeKind::StaticCall) // => ['call', 'usage']
     * @example Everything a class-like writes down about itself is a declaration
     *     \App\Gql\Element\EdgeLabels::belongsTo(\App\Analyzer\Graph\EdgeKind::Attribute) // => ['declaration']
     *
     * @return list<string> The families, most specific first
     */
    public static function belongsTo(EdgeKind $kind): array
    {
        return match ($kind) {
            EdgeKind::FunctionCall,
            EdgeKind::MethodCall,
            EdgeKind::StaticCall => ['call', 'usage'],

            EdgeKind::PossibleCall => ['usage'],

            EdgeKind::Instantiation,
            EdgeKind::PropertyAccess,
            EdgeKind::StaticPropertyAccess,
            EdgeKind::ConstFetch,
            EdgeKind::Instanceof,
            EdgeKind::Catch => ['usage'],

            EdgeKind::DeclarationMethod,
            EdgeKind::DeclarationProperty,
            EdgeKind::DeclarationConstant,
            EdgeKind::DeclarationEnumCase => ['declares', 'declaration'],

            EdgeKind::DeclarationTypeParameter,
            EdgeKind::DeclarationTypeReturn => ['signatureType', 'declaration'],

            EdgeKind::DeclarationTraitUse,
            EdgeKind::DeclarationExtends,
            EdgeKind::DeclarationImplements,
            EdgeKind::DeclarationTypeProperty,
            EdgeKind::PhpDoc,
            EdgeKind::Attribute => ['declaration'],

            EdgeKind::UsedBy,
            EdgeKind::DeclaredIn => ['inverse'],
        };
    }

    /**
     * Returns every label a relation can carry, for a reader asking what there is.
     *
     * The two labels of the relations peq derives rather than reads are left out,
     * because a query never meets one: a pattern reads an edge backwards by pointing
     * its arrow the other way, which is the language's own way of asking.
     *
     * @example Families a pattern can ask for are among them
     *     in_array('call', \App\Gql\Element\EdgeLabels::all(), true) // => true
     * @example The derived readings are not, because no pattern meets one
     *     in_array('usedBy', \App\Gql\Element\EdgeLabels::all(), true) // => false
     *
     * @return list<string> Every label, without repetition
     */
    public static function all(): array
    {
        $labels = [];
        foreach (EdgeKind::cases() as $kind) {
            if ($kind === EdgeKind::UsedBy || $kind === EdgeKind::DeclaredIn) {
                continue;
            }
            foreach (self::of($kind) as $label) {
                $labels[$label] = true;
            }
        }

        return array_keys($labels);
    }
}
