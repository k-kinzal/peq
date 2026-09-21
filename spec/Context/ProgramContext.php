<?php

declare(strict_types=1);

namespace Spec\Context;

use App\Gql\Execution\QueryExecution;
use App\Gql\GqlException;
use App\Gql\Parsing\Parser;
use App\Gql\Result\ResultTable;
use App\Gql\StatusCode;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Assert;

/**
 * Steps of the specification: give a GQL-program, run it, and state what came back.
 *
 * A scenario says three things and no more — the program, that it was run, and the
 * outcome — because that is all ISO/IEC 39075 says an implementation owes a caller: a
 * result table, or a GQLSTATUS. Anything a scenario asserted beyond those would be
 * asserting about peq rather than about GQL.
 */
final class ProgramContext implements Context
{
    /**
     * The GQL-program the scenario gave.
     */
    private string $program = '';

    /**
     * What the program answered, or null when it failed or has not run.
     */
    private ?ResultTable $answered = null;

    /**
     * The condition the program was refused under, or null when it was not refused.
     */
    private ?GqlException $refused = null;

    /**
     * Takes the GQL-program of the scenario.
     *
     * @param PyStringNode $program The text of the program
     */
    #[Given('the GQL-program:')]
    public function theGqlProgram(PyStringNode $program): void
    {
        $this->program = $program->getRaw();
        $this->answered = null;
        $this->refused = null;
    }

    /**
     * Takes a GQL-program written on one line.
     *
     * @param string $program The text of the program
     */
    #[Given('the GQL-program :program')]
    public function theGqlProgramOnOneLine(string $program): void
    {
        $this->program = $program;
        $this->answered = null;
        $this->refused = null;
    }

    /**
     * Reads the program without running it, keeping either success or the condition.
     */
    #[When('the program is read')]
    public function theProgramIsRead(): void
    {
        try {
            Parser::read($this->program);
            $this->answered = ResultTable::nothing();
        } catch (GqlException $condition) {
            $this->refused = $condition;
        }
    }

    /**
     * Runs the program against the graph of the specification.
     */
    #[When('the program is executed')]
    public function theProgramIsExecuted(): void
    {
        try {
            $this->answered = (new QueryExecution(SpecificationGraph::elements()))->query($this->program);
        } catch (GqlException $condition) {
            $this->refused = $condition;
        }
    }

    /**
     * States that the implementation accepted the program as GQL.
     */
    #[Then('the program is accepted')]
    public function theProgramIsAccepted(): void
    {
        Assert::assertNull(
            $this->refused,
            sprintf('The program was refused: %s', $this->refused?->getMessage() ?? ''),
        );
    }

    /**
     * States the GQLSTATUS the implementation reported.
     *
     * @param string $code The five-character code
     */
    #[Then('the GQLSTATUS is :code')]
    public function theGqlstatusIs(string $code): void
    {
        Assert::assertSame($code, $this->statusOfTheOutcome()->value);
    }

    /**
     * States the condition the GQLSTATUS names, in the standard's own wording.
     *
     * @param string $condition The condition
     */
    #[Then('the condition is :condition')]
    public function theConditionIs(string $condition): void
    {
        Assert::assertSame($condition, $this->statusOfTheOutcome()->condition());
    }

    /**
     * States the columns of the result table, by name and by GQL value type.
     *
     * @param string $columns The columns, written `name:TYPE` and separated by commas
     */
    #[Then('the result table has columns :columns')]
    public function theResultTableHasColumns(string $columns): void
    {
        Assert::assertSame($columns, SpecificationOutcome::columnsOf($this->answeredTable()));
    }

    /**
     * States the rows of the result table.
     *
     * @param PyStringNode $rows The rows, one per line, values separated by commas
     */
    #[Then('the result table holds:')]
    public function theResultTableHolds(PyStringNode $rows): void
    {
        Assert::assertSame($rows->getRaw(), SpecificationOutcome::rowsOf($this->answeredTable()));
    }

    /**
     * States that the result table holds one row and what it holds.
     *
     * @param string $row The row, values separated by commas
     */
    #[Then('the result table holds one row :row')]
    public function theResultTableHoldsOneRow(string $row): void
    {
        Assert::assertSame($row, SpecificationOutcome::rowsOf($this->answeredTable()));
    }

    /**
     * States how many rows the result table holds.
     *
     * @param int $rows How many
     */
    #[Then('the result table holds :rows rows')]
    public function theResultTableHoldsRows(int $rows): void
    {
        Assert::assertCount($rows, $this->answeredTable()->rows);
    }

    /**
     * Returns the status of whatever the program produced.
     *
     * @return StatusCode The status
     */
    private function statusOfTheOutcome(): StatusCode
    {
        if ($this->refused !== null) {
            return $this->refused->status;
        }

        return $this->answeredTable()->status();
    }

    /**
     * Returns the result table, failing the scenario when the program was refused.
     *
     * @return ResultTable The table
     */
    private function answeredTable(): ResultTable
    {
        if ($this->answered === null) {
            Assert::fail(sprintf(
                'The program produced no result table: %s',
                $this->refused?->getMessage() ?? 'it has not been run',
            ));
        }

        return $this->answered;
    }
}
