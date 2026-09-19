<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Gql\Datum\StringDatum;
use App\Gql\Syntax\Clause;
use App\Gql\Syntax\Clause\FilterClause;
use App\Gql\Syntax\Clause\LetClause;
use App\Gql\Syntax\Clause\MatchClause;
use App\Gql\Syntax\Clause\OrderByClause;
use App\Gql\Syntax\Clause\PageClause;
use App\Gql\Syntax\Clause\ReturnClause;
use App\Gql\Syntax\Clause\SortDirection;
use App\Gql\Syntax\Clause\SortKey;
use App\Gql\Syntax\Expression;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\CallExpression;
use App\Gql\Syntax\Expression\CaseExpression;
use App\Gql\Syntax\Expression\IndexExpression;
use App\Gql\Syntax\Expression\ListExpression;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\UnaryExpression;
use App\Gql\Syntax\Expression\UnaryOperator;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Pattern\Quantifier;
use App\Gql\Syntax\Query;

/**
 * A parsed query written back out in one canonical form.
 *
 * A test about parsing has to say what shape it expected, and a tree of objects is a
 * poor thing to say it with: an assertion over one nests five levels deep and reports
 * a failure as a diff nobody can read. Writing the shape back out as text makes the
 * expectation one line, and makes a failure say which part of the query came out
 * wrong.
 *
 * The form is not GQL. It is deliberately more explicit than GQL — every grouping
 * parenthesised, every default written out — so that two queries that parse
 * differently can never be written the same way.
 */
final class QuerySpelling
{
    /**
     * Writes a parsed query out.
     *
     * @param Query $query The query
     *
     * @return string The query, in one canonical form
     */
    public static function of(Query $query): string
    {
        $written = self::block($query->blocks[0]);
        foreach ($query->operators as $place => $operator) {
            $written .= ' '.$operator->spelling().' '.self::block($query->blocks[$place + 1]);
        }

        return $written;
    }

    /**
     * Writes one run of clauses out.
     *
     * @param \App\Gql\Syntax\QueryBlock $block The run
     *
     * @return string The run, in one canonical form
     */
    public static function block(\App\Gql\Syntax\QueryBlock $block): string
    {
        return implode(' ', array_map(self::clause(...), $block->clauses));
    }

    /**
     * Writes one clause out.
     *
     * @param Clause $clause The clause
     *
     * @return string The clause, in one canonical form
     */
    public static function clause(Clause $clause): string
    {
        if ($clause instanceof MatchClause) {
            return ($clause->optional ? 'OPTIONAL MATCH ' : 'MATCH ')
                .self::graph($clause->pattern)
                .($clause->where === null ? '' : ' WHERE '.self::expression($clause->where));
        }
        if ($clause instanceof LetClause) {
            $bound = array_map(
                static fn ($binding): string => $binding->name.'='.self::expression($binding->value),
                $clause->bindings,
            );

            return 'LET '.implode(',', $bound);
        }
        if ($clause instanceof FilterClause) {
            return 'FILTER '.self::expression($clause->predicate);
        }
        if ($clause instanceof OrderByClause) {
            return 'ORDER BY '.self::keys($clause->keys);
        }
        if ($clause instanceof PageClause) {
            return self::page($clause);
        }

        return self::projection($clause);
    }

    /**
     * Writes a projection out.
     *
     * @param ReturnClause $clause The projection
     *
     * @return string The projection, in one canonical form
     */
    public static function projection(ReturnClause $clause): string
    {
        $written = 'RETURN'.($clause->distinct ? ' DISTINCT' : '');
        $written .= $clause->everything() ? ' *' : ' '.implode(',', array_map(
            static fn ($column): string => self::expression($column->value).' AS '.$column->heading(),
            $clause->columns,
        ));
        if ($clause->groupBy !== []) {
            $written .= ' GROUP BY '.implode(',', array_map(self::expression(...), $clause->groupBy));
        }
        if ($clause->orderBy !== []) {
            $written .= ' ORDER BY '.self::keys($clause->orderBy);
        }

        return $clause->page === null ? $written : $written.' '.self::page($clause->page);
    }

    /**
     * Writes a stretch of rows out.
     *
     * @param PageClause $clause The stretch
     *
     * @return string The stretch, in one canonical form
     */
    public static function page(PageClause $clause): string
    {
        return 'PAGE off='.$clause->offset.' limit='.($clause->limit ?? 'all');
    }

    /**
     * Writes an ordering out.
     *
     * @param list<SortKey> $keys The keys
     *
     * @return string The keys, in one canonical form
     */
    public static function keys(array $keys): string
    {
        return implode(',', array_map(
            static fn (SortKey $key): string => self::expression($key->value)
                .($key->direction === SortDirection::Descending ? ' DESC' : ' ASC'),
            $keys,
        ));
    }

    /**
     * Writes a graph pattern out.
     *
     * @param GraphPattern $pattern The pattern
     *
     * @return string The pattern, in one canonical form
     */
    public static function graph(GraphPattern $pattern): string
    {
        return implode(', ', array_map(self::path(...), $pattern->paths));
    }

    /**
     * Writes a path pattern out.
     *
     * @param PathPattern $path The path
     *
     * @return string The path, in one canonical form
     */
    public static function path(PathPattern $path): string
    {
        $written = $path->variable === null ? '' : $path->variable.'=';
        $written .= $path->mode === PathMode::Trail ? '' : $path->mode->value.' ';

        return $written.implode('', array_map(self::term(...), $path->terms));
    }

    /**
     * Writes one piece of a path out.
     *
     * @param PathTerm $term The piece
     *
     * @return string The piece, in one canonical form
     */
    public static function term(PathTerm $term): string
    {
        if ($term instanceof GroupPattern) {
            return '('.implode('', array_map(self::term(...), $term->terms)).')'.self::quantifier($term->quantifier);
        }
        if ($term instanceof EdgePattern) {
            return $term->direction->spelling(self::filler($term->variable, $term->labels, $term->filter))
                .self::quantifier($term->quantifier);
        }
        if ($term instanceof NodePattern) {
            return '('.self::filler($term->variable, $term->labels, $term->filter).')';
        }

        return '?';
    }

    /**
     * Writes what a pattern requires of one element out.
     *
     * @param null|string       $variable The name it binds
     * @param null|LabelPattern $labels   What it requires of the labels
     * @param ElementFilter     $filter   What else it requires
     *
     * @return string The requirement, in one canonical form
     */
    public static function filler(?string $variable, ?LabelPattern $labels, ElementFilter $filter): string
    {
        $written = $variable ?? '';
        $written .= $labels === null ? '' : ':'.self::labels($labels);
        if ($filter->properties !== []) {
            $properties = [];
            foreach ($filter->properties as $name => $expected) {
                $properties[] = $name.':'.self::expression($expected);
            }
            $written .= '{'.implode(',', $properties).'}';
        }

        return $filter->predicate === null ? $written : $written.' WHERE '.self::expression($filter->predicate);
    }

    /**
     * Writes a repetition out.
     *
     * @param null|Quantifier $quantifier The repetition, or null when there is none
     *
     * @return string The repetition, in one canonical form
     */
    public static function quantifier(?Quantifier $quantifier): string
    {
        return $quantifier === null ? '' : '{'.$quantifier->least.','.($quantifier->most ?? '').'}';
    }

    /**
     * Writes a label expression out.
     *
     * @param LabelPattern $labels The requirement
     *
     * @return string The requirement, in one canonical form
     */
    public static function labels(LabelPattern $labels): string
    {
        return match ($labels->operator) {
            LabelOperator::Named => (string) $labels->name,
            LabelOperator::Anything => '%',
            LabelOperator::Both => '('.self::labels($labels->operands[0]).'&'.self::labels($labels->operands[1]).')',
            LabelOperator::Either => '('.self::labels($labels->operands[0]).'|'.self::labels($labels->operands[1]).')',
            LabelOperator::Neither => '!'.self::labels($labels->operands[0]),
        };
    }

    /**
     * Writes an expression out, with every grouping made explicit.
     *
     * @param Expression $expression The expression
     *
     * @return string The expression, in one canonical form
     */
    public static function expression(Expression $expression): string
    {
        if ($expression instanceof LiteralExpression) {
            return $expression->value instanceof StringDatum
                ? "'".$expression->value->value."'"
                : $expression->value->toText();
        }
        if ($expression instanceof VariableExpression) {
            return $expression->name;
        }
        if ($expression instanceof PropertyExpression) {
            return self::expression($expression->subject).'.'.$expression->property;
        }
        if ($expression instanceof IndexExpression) {
            return self::expression($expression->subject).'['.self::expression($expression->index).']';
        }
        if ($expression instanceof UnaryExpression) {
            return self::unary($expression);
        }
        if ($expression instanceof BinaryExpression) {
            return '('.self::expression($expression->left).' '.$expression->operator->spelling()
                .' '.self::expression($expression->right).')';
        }
        if ($expression instanceof ListExpression) {
            return '['.implode(',', array_map(self::expression(...), $expression->items)).']';
        }
        if ($expression instanceof CaseExpression) {
            return self::choice($expression);
        }
        if ($expression instanceof CallExpression) {
            return self::call($expression);
        }

        return '?';
    }

    /**
     * Writes an operator applied to one value out.
     *
     * @param UnaryExpression $expression The expression
     *
     * @return string The expression, in one canonical form
     */
    public static function unary(UnaryExpression $expression): string
    {
        $operand = self::expression($expression->operand);
        if ($expression->operator === UnaryOperator::IsNull || $expression->operator === UnaryOperator::IsNotNull) {
            return '('.$operand.' '.$expression->operator->spelling().')';
        }

        return '('.$expression->operator->spelling().' '.$operand.')';
    }

    /**
     * Writes a choice between values out.
     *
     * @param CaseExpression $expression The choice
     *
     * @return string The choice, in one canonical form
     */
    public static function choice(CaseExpression $expression): string
    {
        $written = 'CASE'.($expression->subject === null ? '' : ' '.self::expression($expression->subject));
        foreach ($expression->branches as $branch) {
            $written .= ' WHEN '.self::expression($branch->when).' THEN '.self::expression($branch->then);
        }
        $written .= $expression->otherwise === null ? '' : ' ELSE '.self::expression($expression->otherwise);

        return $written.' END';
    }

    /**
     * Writes a function call out.
     *
     * @param CallExpression $expression The call
     *
     * @return string The call, in one canonical form
     */
    public static function call(CallExpression $expression): string
    {
        $inside = $expression->star ? '*' : implode(',', array_map(self::expression(...), $expression->arguments));

        return $expression->name.'('.($expression->distinct ? 'DISTINCT ' : '').$inside.')';
    }
}
