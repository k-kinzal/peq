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
 * @visibility App\Gql
 */
final class EdgeLabels
{
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
        return match ($kind) {
            EdgeKind::FunctionCall => ['functionCall', 'call', 'usage'],
            EdgeKind::MethodCall => ['methodCall', 'call', 'usage'],
            EdgeKind::StaticCall => ['staticCall', 'call', 'usage'],
            EdgeKind::Instantiation => ['instantiation', 'usage'],
            EdgeKind::PropertyAccess => ['propertyAccess', 'usage'],
            EdgeKind::StaticPropertyAccess => ['staticPropertyAccess', 'usage'],
            EdgeKind::ConstFetch => ['constFetch', 'usage'],
            EdgeKind::Instanceof => ['instanceOf', 'usage'],
            EdgeKind::Catch => ['catches', 'usage'],
            EdgeKind::DeclarationTraitUse => ['traitUse', 'declaration'],
            EdgeKind::DeclarationExtends => ['extends', 'declaration'],
            EdgeKind::DeclarationImplements => ['implements', 'declaration'],
            EdgeKind::DeclarationMethod => ['declaresMethod', 'declares', 'declaration'],
            EdgeKind::DeclarationProperty => ['declaresProperty', 'declares', 'declaration'],
            EdgeKind::DeclarationConstant => ['declaresConstant', 'declares', 'declaration'],
            EdgeKind::DeclarationEnumCase => ['declaresEnumCase', 'declares', 'declaration'],
            EdgeKind::DeclarationTypeParameter => ['parameterType', 'signatureType', 'declaration'],
            EdgeKind::DeclarationTypeReturn => ['returnType', 'signatureType', 'declaration'],
            EdgeKind::DeclarationTypeProperty => ['propertyType', 'declaration'],
            EdgeKind::Attribute => ['attribute', 'declaration'],
            EdgeKind::UsedBy => ['usedBy', 'inverse'],
            EdgeKind::DeclaredIn => ['declaredIn', 'inverse'],
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
