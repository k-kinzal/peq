<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Binding\BindingRow;
use App\Gql\Datum\Datum;
use App\Gql\Datum\DatumOrder;
use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Evaluation\ExpressionEvaluation;
use App\Gql\Evaluation\Logic;
use App\Gql\GqlException;
use App\Gql\Syntax\Pattern\ElementFilter;

/**
 * Whether one element is what a pattern asked for.
 *
 * Three things have to hold, and the order they are asked in is the order they get
 * cheaper to be wrong about. The labels first, because they rule out most of a graph
 * for nothing; then the properties written as equalities, which are a lookup each;
 * then the predicate, which is the only one that has to evaluate an expression.
 *
 * The predicate sees the element bound to its own variable, which is what makes
 * `(p:Method WHERE p.visibility = 'public')` mean what it looks like. For an edge
 * crossed by a repeating pattern the variable is bound to the one edge being
 * considered rather than to the list of all of them, which is GQL's rule and the
 * reason a repeating pattern can be narrowed edge by edge.
 *
 * @visibility App\Gql
 */
final class ElementMatching
{
    /**
     * Reports whether a symbol satisfies what a pattern requires of it.
     *
     * @param NodeDatum            $node       The symbol
     * @param null|string          $variable   The name the pattern binds it to, if any
     * @param ElementFilter        $filter     What the pattern requires beyond its labels
     * @param BindingRow           $row        What is already bound
     * @param ExpressionEvaluation $evaluation How a predicate is worked out
     *
     * @example A symbol with the property a pattern asked for satisfies it
     *     $node = new \App\Gql\Datum\NodeDatum('a', [], ['kind' => new \App\Gql\Datum\StringDatum('method')]);
     *     $parser = new \App\Gql\Parsing\ExpressionParser(\App\Gql\Parsing\TokenReader::of("'method'"));
     *     $filter = new \App\Gql\Syntax\Pattern\ElementFilter(['kind' => $parser->parse()]);
     *     \App\Gql\Matching\ElementMatching::satisfies($node, null, $filter, \App\Gql\Binding\BindingRow::unit(), new \App\Gql\Evaluation\ExpressionEvaluation()) // => true
     *
     * @return bool True when it does
     *
     * @throws GqlException If a requirement cannot be worked out
     */
    public static function satisfies(
        EdgeDatum|NodeDatum $node,
        ?string $variable,
        ElementFilter $filter,
        BindingRow $row,
        ExpressionEvaluation $evaluation,
    ): bool {
        if ($filter->empty()) {
            return true;
        }

        $scope = $variable === null ? $row : $row->with($variable, $node);
        foreach ($filter->properties as $name => $expected) {
            if (DatumOrder::equals($node->property($name), $evaluation->evaluate($expected, $scope)) !== true) {
                return false;
            }
        }
        if ($filter->predicate === null) {
            return true;
        }

        return Logic::holds($evaluation->evaluate($filter->predicate, $scope));
    }

    /**
     * Reports whether an element agrees with what a name is already bound to.
     *
     * Writing the same name twice in one pattern says the two places must be the same
     * element, which is how a query asks for two methods of the same class or two
     * controllers that reach the same repository. It is also how a pattern joins to
     * what an earlier clause found.
     *
     * @param null|string $variable The name the pattern binds, if any
     * @param Datum       $element  The element being considered
     * @param BindingRow  $row      What is already bound
     *
     * @example A name nothing bound agrees with anything
     *     \App\Gql\Matching\ElementMatching::agrees('p', new \App\Gql\Datum\NodeDatum('a'), \App\Gql\Binding\BindingRow::unit()) // => true
     * @example A name bound elsewhere agrees only with the same element
     *     $row = \App\Gql\Binding\BindingRow::unit()->with('p', new \App\Gql\Datum\NodeDatum('a'));
     *     \App\Gql\Matching\ElementMatching::agrees('p', new \App\Gql\Datum\NodeDatum('b'), $row) // => false
     *
     * @return bool True when it agrees
     */
    public static function agrees(?string $variable, Datum $element, BindingRow $row): bool
    {
        if ($variable === null || !$row->has($variable)) {
            return true;
        }

        return DatumOrder::equals($row->value($variable), $element) === true;
    }
}
