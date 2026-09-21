<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use App\Action\Experimental\InspectVariablesAction;
use App\Action\Experimental\InspectVariablesInput;
use App\Analyzer\Graph\Direction;
use App\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InspectVariablesAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InspectVariablesInput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\AnonymousClassNaming::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\AutoloadIndex::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\ClassLikeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Dependency::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Slice::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\ExperimentAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Assignments::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Branches::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Exits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Recording::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\State::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Statements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Flow\Truth::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\Invocation\CallEffects::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\ParsedSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\ExperimentAnalyzer\SourceIndex::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\PhpFileCollector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\SourceParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Config::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\DebugAnalyzerConfig::class)]
final class InspectVariablesActionTest extends TestCase
{
    public function testExecuteSelectsARealVariableOccurrence(): void
    {
        $config = new Config(dirname(__DIR__, 3).'/Fixture/Experimental', Direction::Uses);
        $slice = (new InspectVariablesAction())->execute(new InspectVariablesInput($config, 'Tests\Fixture\Experimental\Flow::calculate', 15, 'value'));
        self::assertCount(1, $slice->roots);
        self::assertSame(15, $slice->nodes[0]->line);
        self::assertSame('$value', $slice->nodes[0]->variable);
        self::assertNotEmpty($slice->edges);
    }

    public function testExecuteTranslatesAnUnsupportedTargetIntoAUserError(): void
    {
        $config = new Config(dirname(__DIR__, 3).'/Fixture/Experimental', Direction::Uses);
        $this->expectException(\App\Action\Experimental\InspectionRejected::class);
        $this->expectExceptionMessage('shared variable storage');
        (new InspectVariablesAction())->execute(new InspectVariablesInput($config, 'Tests\Fixture\Experimental\Flow::invalid', 23));
    }

    public function testExecuteDoesNotMakeALaterOverwriteInfluenceAnEarlierCopy(): void
    {
        $config = new Config(dirname(__DIR__, 3).'/Fixture/Experimental', Direction::UsedBy);
        $slice = (new InspectVariablesAction())->execute(new InspectVariablesInput($config, 'Tests\Fixture\Experimental\Flow::calculate', 16, 'value'));
        self::assertCount(1, $slice->nodes);
        self::assertSame([], $slice->edges);
    }
}
