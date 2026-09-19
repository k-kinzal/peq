<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\Diagram;
use App\Reporter\Diagram\DiagramEdge;
use App\Reporter\Diagram\DiagramNode;
use App\Reporter\Diagram\DiagramRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(DiagramRenderer::class)]
#[UsesClass(Diagram::class)]
#[UsesClass(DiagramEdge::class)]
#[UsesClass(DiagramNode::class)]
#[Small]
final class DiagramRendererTest extends TestCase
{
    public function testRenderWritesEverySymbolOnceWithItsRelationsUnderIt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('App\Invoice', 'class', 'src/Invoice.php:12'));
        $diagram->add(new DiagramNode('App\Invoice::total', 'method', 'src/Invoice.php:20'));
        $diagram->add(new DiagramNode('App\Money::add', 'method', 'src/Money.php:8'));
        $diagram->relate(new DiagramEdge('App\Invoice', 'App\Invoice::total', 'declaresMethod'));
        $diagram->relate(new DiagramEdge('App\Invoice::total', 'App\Money::add', 'methodCall'));

        $output = new BufferedOutput();
        (new DiagramRenderer())->render($diagram, $output);

        self::assertSame(
            <<<'DIAGRAM'
                (1) App\Invoice [class] src/Invoice.php:12
                    └── declaresMethod ──> (2) App\Invoice::total
                (2) App\Invoice::total [method] src/Invoice.php:20
                    └── methodCall ──> (3) App\Money::add
                (3) App\Money::add [method] src/Money.php:8

                DIAGRAM,
            $output->fetch(),
        );
    }

    public function testRenderWritesNothingForADrawingWithNothingInIt(): void
    {
        $output = new BufferedOutput();
        (new DiagramRenderer())->render(new Diagram(), $output);

        self::assertSame('', $output->fetch());
    }

    public function testHeadingIntroducesASymbolByItsNumberItsNameAndWhatItIs(): void
    {
        $diagram = new Diagram();
        $node = new DiagramNode('App\Invoice', 'class', 'src/Invoice.php:12');
        $diagram->add($node);

        self::assertSame('(1) App\Invoice [class] src/Invoice.php:12', (new DiagramRenderer())->heading($diagram, $node));
    }

    public function testHeadingIntroducesASymbolNothingIsKnownAboutByItsNameAlone(): void
    {
        $diagram = new Diagram();
        $node = new DiagramNode('App\Invoice');
        $diagram->add($node);

        self::assertSame('(1) App\Invoice', (new DiagramRenderer())->heading($diagram, $node));
    }

    public function testArrowDrawsARelationAsAnArrowToANumberedSymbol(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));

        self::assertSame(
            '    └── calls ──> (2) b',
            (new DiagramRenderer())->arrow($diagram, new DiagramEdge('a', 'b', 'calls'), true),
        );
    }

    public function testArrowKeepsTheBranchOpenWhenAnotherRelationFollowsIt(): void
    {
        $diagram = new Diagram();
        $diagram->add(new DiagramNode('a'));
        $diagram->add(new DiagramNode('b'));

        self::assertSame(
            '    ├── calls ──> (2) b',
            (new DiagramRenderer())->arrow($diagram, new DiagramEdge('a', 'b', 'calls'), false),
        );
    }
}
