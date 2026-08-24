<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Inspect;

use App\Action\Inspect\InspectAction;
use App\Action\Inspect\InspectActionInput;
use App\Action\Inspect\SymbolNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GeneratedSymbol;
use Tests\Fixture\Config\SampleConfig;

/**
 * @internal
 */
#[CoversClass(InspectAction::class)]
#[Medium]
final class InspectActionTest extends TestCase
{
    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteBuildsTheGraphTheChosenAnalyzerProduces(): void
    {
        $result = (new InspectAction())->execute(new InspectActionInput(
            config: SampleConfig::generated(),
            target: GeneratedSymbol::rootName(),
        ));

        self::assertNotEmpty($result->graph->nodes());
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteResolvesTheRequestedSymbolAgainstThatGraph(): void
    {
        $result = (new InspectAction())->execute(new InspectActionInput(
            config: SampleConfig::generated(),
            target: GeneratedSymbol::rootName(),
        ));

        self::assertSame(GeneratedSymbol::rootName(), $result->symbol->id()->toString());
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteReturnsASymbolTheGraphActuallyHolds(): void
    {
        $result = (new InspectAction())->execute(new InspectActionInput(
            config: SampleConfig::generated(),
            target: GeneratedSymbol::rootName(),
        ));

        self::assertSame($result->symbol, $result->graph->nodeNamed(GeneratedSymbol::rootName()));
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteRejectsASymbolTheAnalyzedGraphDoesNotHold(): void
    {
        $this->expectException(SymbolNotFoundException::class);
        $this->expectExceptionMessage('App\Domain\NeverAnalysed');

        (new InspectAction())->execute(new InspectActionInput(
            config: SampleConfig::generated(),
            target: 'App\Domain\NeverAnalysed',
        ));
    }

    /**
     * @throws SymbolNotFoundException
     */
    public function testExecuteGivesTheDebugAnalyzerItsOwnSettingsRatherThanTheWholeConfiguration(): void
    {
        $shallow = (new InspectAction())->execute(new InspectActionInput(
            config: SampleConfig::generated(depth: 1),
            target: GeneratedSymbol::rootNameAtDepth(1),
        ));

        self::assertCount(1, $shallow->graph->nodes());
    }
}
