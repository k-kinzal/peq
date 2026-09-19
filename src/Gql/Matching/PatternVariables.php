<?php

declare(strict_types=1);

namespace App\Gql\Matching;

use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathTerm;

/**
 * The names a pattern would bind if it matched.
 *
 * Only one clause needs this, and it needs it for the case where the pattern did not
 * match. An optional match keeps the row it was given, and GQL says the names the
 * pattern would have bound are absent on that row rather than unbound — the
 * difference being that an absent value can be asked about and an unbound name is a
 * mistake in the query.
 *
 * Working the names out from the pattern rather than from a match is the only way to
 * know them when there was no match to read them off.
 *
 * @visibility App\Gql
 */
final class PatternVariables
{
    /**
     * Returns every name a pattern would bind.
     *
     * @param GraphPattern $pattern The pattern
     *
     * @example A pattern binds the names written in it, paths included
     *     $terms = [new \App\Gql\Syntax\Pattern\NodePattern('a'), new \App\Gql\Syntax\Pattern\EdgePattern(\App\Gql\Syntax\Pattern\EdgeDirection::Along, 'e'), new \App\Gql\Syntax\Pattern\NodePattern('b')];
     *     $path = new \App\Gql\Syntax\Pattern\PathPattern($terms, \App\Gql\Syntax\Pattern\PathMode::Trail, 'p');
     *     \App\Gql\Matching\PatternVariables::of(new \App\Gql\Syntax\Pattern\GraphPattern([$path])) // => ['p', 'a', 'e', 'b']
     *
     * @return list<string> The names, in the order they are written
     */
    public static function of(GraphPattern $pattern): array
    {
        $names = [];
        foreach ($pattern->paths as $path) {
            if ($path->variable !== null) {
                $names[$path->variable] = true;
            }
            foreach (self::inTerms($path->terms) as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Returns every name the pieces of a path would bind.
     *
     * @param list<PathTerm> $terms The pieces of the path
     *
     * @example A parenthesised stretch of pattern binds the names inside it
     *     $group = new \App\Gql\Syntax\Pattern\GroupPattern([new \App\Gql\Syntax\Pattern\NodePattern('a')]);
     *     \App\Gql\Matching\PatternVariables::inTerms([$group]) // => ['a']
     *
     * @return list<string> The names, in the order they are written
     */
    public static function inTerms(array $terms): array
    {
        $names = [];
        foreach ($terms as $term) {
            if ($term instanceof GroupPattern) {
                array_push($names, ...self::inTerms($term->terms));

                continue;
            }
            $variable = $term instanceof NodePattern || $term instanceof EdgePattern ? $term->variable : null;
            if ($variable !== null) {
                $names[] = $variable;
            }
        }

        return $names;
    }
}
