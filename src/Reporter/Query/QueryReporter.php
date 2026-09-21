<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Result\ResultTable;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for writing down what a query answered.
 *
 * A query answers with a table, and a table is read by very different readers: a
 * program wants fields it can address, a person wants columns they can scan, and
 * someone who has just narrowed a graph down to a handful of symbols wants to see how
 * those symbols are wired. Those are ways of writing one answer rather than four
 * answers, which is why the query runs once and the reporter is chosen afterwards.
 */
interface QueryReporter
{
    /**
     * Writes down what a query answered.
     *
     * @param ResultTable     $result What the query answered
     * @param OutputInterface $output Where it is written
     */
    public function report(ResultTable $result, OutputInterface $output): void;
}
