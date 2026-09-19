<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectAction;
use App\Action\Inspect\InspectActionInput;
use App\Action\Inspect\SymbolNotFoundException;
use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\DebugAnalyzerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InspectAction::class)]
#[UsesClass(\App\Analyzer\DebugAnalyzer\Generator\RandomSource::class)]
#[Medium]
final class InspectActionTest extends TestCase
{
    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteBuildsTheGraphTheChosenAnalyzerProduces(): void
    {
        $result = (new InspectAction())->execute(new InspectActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            target: (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString(),
        ));

        self::assertNotSame([], $result->graph->nodes());
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteResolvesTheRequestedSymbolAgainstThatGraph(): void
    {
        $root = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString();
        $result = (new InspectAction())->execute(new InspectActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            target: $root,
        ));

        self::assertSame($root, $result->symbol->id()->toString());
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteReturnsASymbolTheGraphActuallyHolds(): void
    {
        $root = (new DebugAnalyzer(seed: 42, depth: 3))->analyze('/generated')->nodes()[0]->id()->toString();
        $result = (new InspectAction())->execute(new InspectActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            target: $root,
        ));

        self::assertSame($result->symbol, $result->graph->nodeNamed($root));
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteRejectsASymbolTheAnalyzedGraphDoesNotHold(): void
    {
        $this->expectException(SymbolNotFoundException::class);
        $this->expectExceptionMessage('App\Domain\NeverAnalysed');

        (new InspectAction())->execute(new InspectActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 3, seed: 42)),
            target: 'App\Domain\NeverAnalysed',
        ));
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteGivesTheDebugAnalyzerItsOwnSettingsRatherThanTheWholeConfiguration(): void
    {
        $shallow = (new InspectAction())->execute(new InspectActionInput(
            config: new Config(basePath: '.', direction: Direction::Uses, analyzer: AnalyzerKind::Debug, debug: new DebugAnalyzerConfig(depth: 1, seed: 42)),
            target: (new DebugAnalyzer(seed: 42, depth: 1))->analyze('/generated')->nodes()[0]->id()->toString(),
        ));

        self::assertCount(1, $shallow->graph->nodes());
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteReadsRealSourcesWhenTheConfigurationAsksForThem(): void
    {
        $this->expectException(SymbolNotFoundException::class);
        $this->expectExceptionMessage('App\Domain\NeverAnalysed');

        (new InspectAction())->execute(new InspectActionInput(
            config: new Config(basePath: __DIR__.'/nonexistent', direction: Direction::Uses, analyzer: AnalyzerKind::PhpStan),
            target: 'App\Domain\NeverAnalysed',
        ));
    }
}
