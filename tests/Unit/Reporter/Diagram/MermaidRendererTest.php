<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\MermaidRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(MermaidRenderer::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[Small]
final class MermaidRendererTest extends TestCase
{
    public function testRenderWritesTheFlowchartOneLineAtATime(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Invoice'));
        $diagram->add(new DiagramNode('App\Invoice::total'));
        $diagram->relate(new DiagramEdge('App\Invoice', 'App\Invoice::total', 'declaration-method'));
        $output = new BufferedOutput();

        (new MermaidRenderer())->render($diagram, $output);

        self::assertSame(
            <<<'MERMAID'
                flowchart LR
                    n1["App\Invoice"]
                    n2["App\Invoice::total"]
                    n1 -->|"declaration-method"| n2

                MERMAID,
            $output->fetch(),
        );
    }

    public function testRenderWritesNothingForADrawingThatHoldsNothing(): void
    {
        $output = new BufferedOutput();

        (new MermaidRenderer())->render(new Diagram(), $output);

        self::assertSame('', $output->fetch());
    }

    public function testLinesNumbersTheSymbolsAndLabelsEachArrowWithWhatItIs(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\A'));
        $diagram->add(new DiagramNode('App\B'));
        $diagram->relate(new DiagramEdge('App\A', 'App\B', 'calls'));

        self::assertSame(
            ['flowchart LR', '    n1["App\A"]', '    n2["App\B"]', '    n1 -->|"calls"| n2'],
            (new MermaidRenderer())->lines($diagram),
        );
    }

    public function testLinesIsNoFlowchartForADrawingThatHoldsNothing(): void
    {
        self::assertSame([], (new MermaidRenderer())->lines(new Diagram()));
    }

    public function testLinesWritesASymbolNothingIsJoinedToAsASymbolAlone(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\A'));

        self::assertSame(['flowchart LR', '    n1["App\A"]'], (new MermaidRenderer())->lines($diagram));
    }

    public function testLinesDrawsARelationWithNoNameAsAnArrowWithNoLabel(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b'));

        self::assertSame(
            ['flowchart LR', '    n1["a"]', '    n2["b"]', '    n1 --> n2'],
            (new MermaidRenderer())->lines($diagram),
        );
    }

    public function testLinesWritesTheArrowsOfEachSymbolInTheOrderTheSymbolsWereDrawn(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->add(new DiagramNode('c'));
        $diagram->relate(new DiagramEdge('b', 'c', 'calls'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));

        self::assertSame(
            [
                'flowchart LR',
                '    n1["a"]',
                '    n2["b"]',
                '    n3["c"]',
                '    n1 -->|"calls"| n2',
                '    n2 -->|"calls"| n3',
            ],
            (new MermaidRenderer())->lines($diagram),
        );
    }

    public function testLinesDrawsEveryRelationBetweenTheSameTwoSymbols(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));
        $diagram->relate(new DiagramEdge('a', 'b', 'names'));

        self::assertSame(
            ['flowchart LR', '    n1["a"]', '    n2["b"]', '    n1 -->|"calls"| n2', '    n1 -->|"names"| n2'],
            (new MermaidRenderer())->lines($diagram),
        );
    }

    public function testLinesDrawsASymbolPointingAtItselfAsAnArrowBackToItself(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->relate(new DiagramEdge('a', 'a', 'calls'));

        self::assertSame(['flowchart LR', '    n1["a"]', '    n1 -->|"calls"| n1'], (new MermaidRenderer())->lines($diagram));
    }

    public function testLinesLeavesOutARelationToASymbolTheDrawingDoesNotHold(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->relate(new DiagramEdge('a', 'b', 'calls'));

        self::assertSame(['flowchart LR', '    n1["a"]'], (new MermaidRenderer())->lines($diagram));
    }

    public function testLinesEscapesWhatANameOrALabelHoldsThatMermaidWouldReadAsSyntax(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('say "hi"'));
        $diagram->add(new DiagramNode('array<int>'));
        $diagram->relate(new DiagramEdge('say "hi"', 'array<int>', '#1'));

        self::assertSame(
            ['flowchart LR', '    n1["say #quot;hi#quot;"]', '    n2["array#lt;int#gt;"]', '    n1 -->|"#35;1"| n2'],
            (new MermaidRenderer())->lines($diagram),
        );
    }

    public function testEscapeWritesADoubleQuoteAsAnEntity(): void
    {
        self::assertSame('say #quot;hi#quot;', MermaidRenderer::escape('say "hi"'));
    }

    public function testEscapeWritesAngleBracketsAsEntities(): void
    {
        self::assertSame('array#lt;int, string#gt;', MermaidRenderer::escape('array<int, string>'));
    }

    public function testEscapeWritesTheCharacterThatBeginsAnEntityAsAnEntity(): void
    {
        self::assertSame('#35;quot;', MermaidRenderer::escape('#quot;'));
    }

    public function testEscapeDoesNotEscapeTheEntitiesItWrites(): void
    {
        self::assertSame('#35;#quot;', MermaidRenderer::escape('#"'));
    }

    public function testEscapeLeavesANameAsItIs(): void
    {
        self::assertSame('App\Http\Controller::show', MermaidRenderer::escape('App\Http\Controller::show'));
    }

    public function testEscapeLeavesAWideNameAsItIs(): void
    {
        self::assertSame('請求書', MermaidRenderer::escape('請求書'));
    }
}
