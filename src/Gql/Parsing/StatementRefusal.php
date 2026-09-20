<?php

declare(strict_types=1);

namespace App\Gql\Parsing;

use App\Gql\GqlException;
use App\Gql\StatusCode;

/**
 * The GQL statements peq reads well enough to refuse by name.
 *
 * peq answers questions about source code it has just read out of a directory. A
 * statement that changes a graph, manages a session or declares a graph type would be
 * describing a database that does not exist, so none of them is implemented — but
 * there are two ways not to implement a statement, and only one of them is honest.
 *
 * Saying "expected a clause" tells a reader their GQL is wrong, which it is not: they
 * wrote a statement the standard defines and peq chose not to answer. Saying so by
 * name, under the status GQL gives a query it will not run, tells them the true
 * thing — and tells a program the true thing too, since the code says "the query was
 * at fault" rather than "the query could not be read".
 *
 * @visibility App\Gql\Parsing
 */
final class StatementRefusal
{
    /**
     * The statements GQL defines that peq does not run, and what each one does.
     */
    private const STATEMENTS = [
        'INSERT' => 'adds nodes and edges to a graph',
        'SET' => 'assigns properties or labels',
        'REMOVE' => 'takes properties or labels away',
        'DELETE' => 'removes nodes and edges from a graph',
        'DETACH' => 'removes a node together with its edges',
        'NODETACH' => 'removes a node only when it has no edges',
        'CALL' => 'invokes a procedure',
        'USE' => 'chooses the graph the statements after it run against',
        'CREATE' => 'declares a graph, a graph type or a schema',
        'DROP' => 'discards a graph, a graph type or a schema',
        'SESSION' => 'sets or resets a session parameter',
        'START' => 'begins a transaction',
        'COMMIT' => 'ends a transaction, keeping what it did',
        'ROLLBACK' => 'ends a transaction, undoing what it did',
        'FINISH' => 'ends a query without a result table',
        'NEXT' => 'chains one query onto the result of another',
        'YIELD' => 'names the columns a called procedure returns',
    ];

    /**
     * Reports the statement standing here, when it is one peq does not run.
     *
     * @param TokenReader $tokens The pieces of the query being read
     *
     * @example A statement that would change the graph is refused by name
     *     \App\Gql\Parsing\StatementRefusal::reject(\App\Gql\Parsing\TokenReader::of('INSERT (p:Person)')) // throws \App\Gql\GqlException: adds nodes and edges to a graph
     * @example Anything else is left for the grammar to report
     *     \App\Gql\Parsing\StatementRefusal::reject(\App\Gql\Parsing\TokenReader::of('banana')) // => null
     *
     * @throws GqlException If the statement is one GQL defines and peq does not run
     */
    public static function reject(TokenReader $tokens): void
    {
        foreach (self::STATEMENTS as $keyword => $does) {
            if (!$tokens->atKeyword($keyword)) {
                continue;
            }

            throw GqlException::because(
                StatusCode::UnknownFeature,
                sprintf(
                    '%s %s, and peq answers questions about source code rather than keeping a graph, so it does not run one',
                    $keyword,
                    $does,
                ),
            );
        }
    }

    /**
     * Returns every statement peq refuses by name, and what each one does.
     *
     * @example The statements that would change a graph are among them
     *     array_key_exists('INSERT', \App\Gql\Parsing\StatementRefusal::all()) // => true
     *
     * @return array<string, string> What the statement does, by the keyword it begins with
     */
    public static function all(): array
    {
        return self::STATEMENTS;
    }
}
