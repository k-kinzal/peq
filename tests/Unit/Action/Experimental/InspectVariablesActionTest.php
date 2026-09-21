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
