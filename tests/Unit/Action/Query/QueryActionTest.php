<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Query;

use App\Action\AnalyzerChoice;
use App\Action\Query\QueryAction;
use App\Action\Query\QueryActionInput;
use App\Action\Query\QueryActionOutput;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\DebugAnalyzer\Generator\RandomSource;
use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\DebugAnalyzerConfig;
use App\Gql\Element\GraphProjection;
use App\Gql\Execution\QueryExecution;
use App\Gql\GqlException;
use App\Gql\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryAction::class)]
#[UsesClass(AnalyzerChoice::class)]
#[UsesClass(DebugAnalyzer::class)]
#[UsesClass(RandomSource::class)]
#[UsesClass(Direction::class)]
#[UsesClass(AnalyzerKind::class)]
#[UsesClass(Config::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[UsesClass(GraphProjection::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(QueryActionInput::class)]
#[UsesClass(QueryActionOutput::class)]
#[Medium]
final class QueryActionTest extends TestCase
{
    /**
     * @throws GqlException
     */
    public function testExecuteAnswersTheQueryAgainstTheGraphTheAnalyzerProduced(): void
    {
        $answered = (new QueryAction())->execute(new QueryActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            query: 'MATCH (p:Method) RETURN count(*) AS n',
        ));

        self::assertSame('11', $answered->result->rows[0]->value(0)->toText());
    }

    /**
     * @throws GqlException
     */
    public function testExecuteCarriesTheGraphBackSoTheAnswerCanBeDrawn(): void
    {
        $answered = (new QueryAction())->execute(new QueryActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            query: 'MATCH (p:Class) RETURN p',
        ));

        self::assertNotSame([], $answered->graph->nodes());
    }

    /**
     * @throws GqlException
     */
    public function testExecuteSaysThatAQueryFoundNothingRatherThanFailing(): void
    {
        $answered = (new QueryAction())->execute(new QueryActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            query: "MATCH (p:Method WHERE p.name = 'nothingIsCalledThis') RETURN p",
        ));

        self::assertSame(StatusCode::NoData, $answered->result->status());
    }

    /**
     * @throws GqlException
     */
    public function testExecuteReportsAQueryThatCannotBeRead(): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a RETURN statement');

        (new QueryAction())->execute(new QueryActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            query: 'MATCH (p) RETRUN p',
        ));
    }
}
