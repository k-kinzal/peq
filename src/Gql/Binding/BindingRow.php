<?php

declare(strict_types=1);

namespace App\Gql\Binding;

use App\Gql\Datum\Datum;
use App\Gql\Datum\NullDatum;

/**
 * One row of what a query knows so far: a name bound to a value, several times over.
 *
 * Everything a GQL query does happens to rows of these. A pattern turns one row into
 * as many as the graph matches it; a naming adds a column to each; a filter drops
 * some; a projection replaces them with what the reader asked for. A variable is
 * nothing more than a column of the row currently being worked on, which is why
 * writing the same name twice in a pattern joins: the second occurrence has to agree
 * with the column the first one wrote.
 *
 * Rows are values. Adding a binding produces another row rather than changing this
 * one, because a pattern that matches three ways has to hand back three rows that
 * agree about everything they were given and differ in what they found.
 *
 * @visibility App\Gql
 */
final class BindingRow
{
    /**
     * @param array<string, Datum> $values What each bound name is bound to
     */
    public function __construct(
        private readonly array $values = [],
    ) {}

    /**
     * Returns the row a query starts from: one row, binding nothing.
     *
     * A query begins with a single row so that its first clause runs once. Beginning
     * with no rows would make every query answer nothing, and beginning with no row
     * at all would make the first clause a special case.
     *
     * @example A query starts knowing nothing, but knowing it once
     *     \App\Gql\Binding\BindingRow::unit()->names() // => []
     *
     * @return self The row a query starts from
     */
    public static function unit(): self
    {
        return new self();
    }

    /**
     * Reports whether a name is bound in this row.
     *
     * @param string $name The name
     *
     * @example A name nothing bound is not bound
     *     \App\Gql\Binding\BindingRow::unit()->has('p') // => false
     *
     * @return bool True when it is
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * Returns what a name is bound to.
     *
     * A name that is bound to nothing and a name that is not bound at all read the
     * same way here. Telling them apart is the business of whoever cares — and the
     * only one that does is the evaluator, which reports an unbound name as a
     * mistake in the query rather than as an absent value.
     *
     * @param string $name The name
     *
     * @example A name that was bound reads as what it was bound to
     *     \App\Gql\Binding\BindingRow::unit()->with('n', new \App\Gql\Datum\IntegerDatum(3))->value('n')->toText() // => '3'
     * @example A name that was not reads as nothing
     *     \App\Gql\Binding\BindingRow::unit()->value('p')->kind() // => \App\Gql\Datum\DatumKind::Null
     *
     * @return Datum The value, or the absence of one
     */
    public function value(string $name): Datum
    {
        return $this->values[$name] ?? new NullDatum();
    }

    /**
     * Returns this row with one more name bound.
     *
     * @param string $name  The name
     * @param Datum  $value What to bind it to
     *
     * @example Binding a name leaves the row it was bound in alone
     *     $before = \App\Gql\Binding\BindingRow::unit();
     *     $before->with('n', new \App\Gql\Datum\IntegerDatum(3));
     *     $before->has('n') // => false
     *
     * @return self The row with that binding
     */
    public function with(string $name, Datum $value): self
    {
        return new self([...$this->values, $name => $value]);
    }

    /**
     * Returns this row with several more names bound.
     *
     * @param array<string, Datum> $values What each name is bound to
     *
     * @example Everything a pattern found is bound at once
     *     \App\Gql\Binding\BindingRow::unit()->withAll(['a' => new \App\Gql\Datum\IntegerDatum(1)])->names() // => ['a']
     *
     * @return self The row with those bindings
     */
    public function withAll(array $values): self
    {
        return $values === [] ? $this : new self([...$this->values, ...$values]);
    }

    /**
     * Returns the names bound in this row, in the order they were bound.
     *
     * The order matters: a query that asks for everything in scope shows its columns
     * in it, and a reader expects the columns of `MATCH (p)-[e]->(q) RETURN *` in the
     * order the pattern wrote them.
     *
     * @example Names come back in the order they were bound
     *     \App\Gql\Binding\BindingRow::unit()->withAll(['p' => new \App\Gql\Datum\NullDatum(), 'e' => new \App\Gql\Datum\NullDatum()])->names() // => ['p', 'e']
     *
     * @return list<string> The names
     */
    public function names(): array
    {
        return array_keys($this->values);
    }

    /**
     * Returns everything bound in this row.
     *
     * @example A row that binds nothing holds nothing
     *     \App\Gql\Binding\BindingRow::unit()->values() // => []
     *
     * @return array<string, Datum> What each bound name is bound to
     */
    public function values(): array
    {
        return $this->values;
    }
}
