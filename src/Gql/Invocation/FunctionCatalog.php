<?php

declare(strict_types=1);

namespace App\Gql\Invocation;

use App\Gql\Datum\Datum;
use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * Which functions a query can call, and what calling one does.
 *
 * Names are matched without regard to case, as GQL matches every name a grammar
 * reserves, so `COUNT` and `count` and `Coalesce` are the functions a reader expects
 * them to be. A name that belongs to no function is reported as an unsupported
 * feature rather than as a missing value, because a query calling a function that
 * does not exist is a query that will never mean what it says.
 *
 * `trim` is the one name that does two things, and which one it does depends on how
 * many arguments it is given: one string is trimmed of its whitespace, and a list
 * with a length is cut to that length. That is GQL's own arrangement.
 *
 * @visibility App\Gql
 */
final class FunctionCatalog
{
    /**
     * Calls a function.
     *
     * @param string      $name      The function name, as the query wrote it
     * @param list<Datum> $arguments What it is applied to, in order
     *
     * @example A function is called by the name a query asks for it by
     *     \App\Gql\Invocation\FunctionCatalog::call('upper', [new \App\Gql\Datum\StringDatum('a')])->toText() // => 'A'
     * @example A name that belongs to nothing says so
     *     \App\Gql\Invocation\FunctionCatalog::call('sqrt', []) // throws \App\Gql\GqlException: unsupported feature
     * @example So does a call given the wrong number of arguments
     *     \App\Gql\Invocation\FunctionCatalog::call('upper', []) // throws \App\Gql\GqlException: syntax error
     *
     * @return Datum What the function produced
     *
     * @throws GqlException If no function goes by that name, or it cannot take these arguments
     */
    public static function call(string $name, array $arguments): Datum
    {
        $given = count($arguments);
        $called = strtolower($name);

        return match ($called) {
            'char_length', 'upper', 'lower', 'trim' => self::text($called, $arguments),
            'size' => ListFunctions::size(self::argument($name, $arguments, 0, 1)),
            'nodes' => GraphFunctions::nodes(self::argument($name, $arguments, 0, 1)),
            'edges' => GraphFunctions::edges(self::argument($name, $arguments, 0, 1)),
            'elements' => GraphFunctions::elements(self::argument($name, $arguments, 0, 1)),
            'labels' => GraphFunctions::labels(self::argument($name, $arguments, 0, 1)),
            'path_length' => GraphFunctions::pathLength(self::argument($name, $arguments, 0, 1)),
            'to_json_string' => GeneralFunctions::toJsonString(self::argument($name, $arguments, 0, 1)),
            'string_join' => TextFunctions::join(self::argument($name, $arguments, 0, 2), self::argument($name, $arguments, 1, 2)),
            'nullif' => GeneralFunctions::nullif(self::argument($name, $arguments, 0, 2), self::argument($name, $arguments, 1, 2)),
            'coalesce' => self::atLeastOne($name, $arguments),
            'zoned_datetime' => self::moment($name, $arguments, $given),
            default => throw GqlException::because(
                StatusCode::UnknownFeature,
                sprintf('there is no function called "%s"', $name),
            ),
        };
    }

    /**
     * Calls one of the functions that take a string, or the list form of trim.
     *
     * @param string      $called    The function name, in lower case
     * @param list<Datum> $arguments What it is applied to, in order
     *
     * @example Trimming a string takes its whitespace off
     *     \App\Gql\Invocation\FunctionCatalog::text('trim', [new \App\Gql\Datum\StringDatum(' a ')])->toText() // => 'a'
     * @example Trimming a list with a length cuts it to that length
     *     $rows = new \App\Gql\Datum\ListDatum([new \App\Gql\Datum\IntegerDatum(1), new \App\Gql\Datum\IntegerDatum(2)]);
     *     \App\Gql\Invocation\FunctionCatalog::text('trim', [$rows, new \App\Gql\Datum\IntegerDatum(1)])->toText() // => '[1]'
     *
     * @return Datum What the function produced
     *
     * @throws GqlException If the call cannot take these arguments
     */
    public static function text(string $called, array $arguments): Datum
    {
        if ($called === 'trim' && count($arguments) === 2) {
            return ListFunctions::trimTo($arguments[0], $arguments[1]);
        }

        $value = self::argument($called, $arguments, 0, 1);

        return match ($called) {
            'char_length' => TextFunctions::charLength($value),
            'upper' => TextFunctions::upper($value),
            'lower' => TextFunctions::lower($value),
            default => TextFunctions::trim($value),
        };
    }

    /**
     * Returns one argument of a call, having checked how many it was given.
     *
     * @param string      $name      The function name, as the query wrote it
     * @param list<Datum> $arguments What it was applied to
     * @param int         $index     Which argument to return, counting from zero
     * @param int         $expected  How many it must have been given
     *
     * @example An argument is returned when the count is right
     *     \App\Gql\Invocation\FunctionCatalog::argument('upper', [new \App\Gql\Datum\StringDatum('a')], 0, 1)->toText() // => 'a'
     * @example A call given the wrong number of arguments says so
     *     \App\Gql\Invocation\FunctionCatalog::argument('upper', [], 0, 1) // throws \App\Gql\GqlException: syntax error
     *
     * @return Datum The argument
     *
     * @throws GqlException If the call was given the wrong number of arguments
     */
    public static function argument(string $name, array $arguments, int $index, int $expected): Datum
    {
        if (count($arguments) !== $expected) {
            throw GqlException::because(
                StatusCode::SyntaxError,
                sprintf('%s takes %d argument(s), and was given %d', $name, $expected, count($arguments)),
            );
        }

        return $arguments[$index];
    }

    /**
     * Calls a function that takes any number of arguments, but at least one.
     *
     * @param string      $name      The function name, as the query wrote it
     * @param list<Datum> $arguments What it was applied to
     *
     * @example The first value that is there is the answer
     *     $values = [new \App\Gql\Datum\NullDatum(), new \App\Gql\Datum\StringDatum('b')];
     *     \App\Gql\Invocation\FunctionCatalog::atLeastOne('coalesce', $values)->toText() // => 'b'
     * @example A call given nothing to choose between says so
     *     \App\Gql\Invocation\FunctionCatalog::atLeastOne('coalesce', []) // throws \App\Gql\GqlException: syntax error
     *
     * @return Datum What the function produced
     *
     * @throws GqlException If the call was given nothing
     */
    public static function atLeastOne(string $name, array $arguments): Datum
    {
        if ($arguments === []) {
            throw GqlException::because(
                StatusCode::SyntaxError,
                sprintf('%s takes at least one argument, and was given none', $name),
            );
        }

        return GeneralFunctions::coalesce($arguments);
    }

    /**
     * Calls the function that reads a moment in time.
     *
     * @param string      $name      The function name, as the query wrote it
     * @param list<Datum> $arguments What it was applied to
     * @param int         $given     How many arguments it was given
     *
     * @example Asked for nothing, it answers with now
     *     \App\Gql\Invocation\FunctionCatalog::moment('zoned_datetime', [], 0)->kind() // => \App\Gql\Datum\DatumKind::DateTime
     * @example Asked for too much, it says so
     *     $twice = [new \App\Gql\Datum\StringDatum('a'), new \App\Gql\Datum\StringDatum('b')];
     *     \App\Gql\Invocation\FunctionCatalog::moment('zoned_datetime', $twice, 2) // throws \App\Gql\GqlException: syntax error
     *
     * @return Datum The moment
     *
     * @throws GqlException If the call was given more than one argument, or one that is not a moment
     */
    public static function moment(string $name, array $arguments, int $given): Datum
    {
        if ($given > 1) {
            throw GqlException::because(
                StatusCode::SyntaxError,
                sprintf('%s takes at most one argument, and was given %d', $name, $given),
            );
        }

        return GeneralFunctions::zonedDatetime($arguments);
    }

    /**
     * Returns the names of every function, for a reader asking what there is.
     *
     * @example The functions that take a path apart are among them
     *     in_array('path_length', \App\Gql\Invocation\FunctionCatalog::all(), true) // => true
     *
     * @return list<string> The names
     */
    public static function all(): array
    {
        return [
            'char_length', 'upper', 'lower', 'trim', 'string_join',
            'size', 'nodes', 'edges', 'elements', 'labels', 'path_length',
            'coalesce', 'nullif', 'to_json_string', 'zoned_datetime',
        ];
    }
}
