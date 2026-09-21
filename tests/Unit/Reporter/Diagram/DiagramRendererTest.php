<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramCanvas;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\DiagramRenderer;
use App\Reporter\Diagram\Layout\DiagramLayout;
use App\Reporter\Diagram\Layout\Lane;
use App\Reporter\Diagram\Layout\LaneRouting;
use App\Reporter\Diagram\Layout\LayeredLayout;
use App\Reporter\Diagram\Layout\LayerOrdering;
use App\Reporter\Diagram\Layout\LayoutItem;
use App\Reporter\Diagram\Layout\LayoutItemKind;
use App\Reporter\Diagram\Layout\RowPlacement;
use App\Reporter\Diagram\MermaidRenderer;
use App\Reporter\Diagram\TerminalRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(TerminalRenderer::class)]
#[CoversClass(MermaidRenderer::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramCanvas::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[UsesClass(DiagramLayout::class)]
#[UsesClass(Lane::class)]
#[UsesClass(LaneRouting::class)]
#[UsesClass(LayeredLayout::class)]
#[UsesClass(LayerOrdering::class)]
#[UsesClass(LayoutItem::class)]
#[UsesClass(LayoutItemKind::class)]
#[UsesClass(RowPlacement::class)]
#[Small]
final class DiagramRendererTest extends TestCase
{
    #[DataProvider('providerEveryRenderer')]
    public function testRenderWritesNothingForADrawingThatHoldsNothing(DiagramRenderer $renderer): void
    {
        $output = new BufferedOutput();

        $renderer->render(new Diagram(), $output);

        self::assertSame('', $output->fetch());
    }

    #[DataProvider('providerEveryRenderer')]
    public function testRenderWritesNowhereElseThanTheGivenOutput(DiagramRenderer $renderer): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Invoice'));
        $diagram->add(new DiagramNode('App\Invoice::total'));
        $diagram->relate(new DiagramEdge('App\Invoice', 'App\Invoice::total', 'declaration-method'));
        $output = new BufferedOutput();
        $this->expectOutputString('');

        $renderer->render($diagram, $output);

        self::assertStringContainsString('App\Invoice::total', $output->fetch());
    }

    #[DataProvider('providerEveryRenderer')]
    public function testRenderMentionsEverySymbolOfTheDrawingByItsName(DiagramRenderer $renderer): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Http\Controller'));
        $diagram->add(new DiagramNode('App\Http\Kernel'));
        $diagram->add(new DiagramNode('App\Domain\Invoice'));
        $diagram->add(new DiagramNode('App\Cache\Store'));
        $diagram->relate(new DiagramEdge('App\Http\Controller', 'App\Http\Kernel', 'declaration-extends'));
        $diagram->relate(new DiagramEdge('App\Http\Controller', 'App\Domain\Invoice', 'type-parameter'));
        $diagram->relate(new DiagramEdge('App\Http\Kernel', 'App\Cache\Store', 'type-property'));
        $diagram->relate(new DiagramEdge('App\Domain\Invoice', 'App\Cache\Store', 'type-property'));
        $output = new BufferedOutput();

        $renderer->render($diagram, $output);
        $written = $output->fetch();

        self::assertStringContainsString('App\Http\Controller', $written);
        self::assertStringContainsString('App\Http\Kernel', $written);
        self::assertStringContainsString('App\Domain\Invoice', $written);
        self::assertStringContainsString('App\Cache\Store', $written);
    }

    #[DataProvider('providerEveryRenderer')]
    public function testRenderMentionsASymbolNothingIsJoinedTo(DiagramRenderer $renderer): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Invoice'));
        $output = new BufferedOutput();

        $renderer->render($diagram, $output);

        self::assertStringContainsString('App\Invoice', $output->fetch());
    }

    #[DataProvider('providerEveryRenderer')]
    public function testRenderLeavesOutASymbolTheDrawingDoesNotHold(DiagramRenderer $renderer): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Invoice'));
        $diagram->relate(new DiagramEdge('App\Invoice', 'App\Money', 'method-call'));
        $output = new BufferedOutput();

        $renderer->render($diagram, $output);

        self::assertStringNotContainsString('App\Money', $output->fetch());
    }

    #[DataProvider('providerEveryRenderer')]
    public function testRenderEndsWhatItWritesWithANewLine(DiagramRenderer $renderer): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Invoice'));
        $output = new BufferedOutput();

        $renderer->render($diagram, $output);

        self::assertStringEndsWith("\n", $output->fetch());
    }

    /**
     * @return iterable<string, array{DiagramRenderer}>
     */
    public static function providerEveryRenderer(): iterable
    {
        yield 'the drawing in the terminal' => [new TerminalRenderer(80)];

        yield 'the drawing in a narrow terminal' => [new TerminalRenderer(1)];

        yield 'the Mermaid flowchart' => [new MermaidRenderer()];
    }
}
