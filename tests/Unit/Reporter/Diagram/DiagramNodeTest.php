<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Diagram;

use App\Reporter\Diagram\DiagramNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DiagramNode::class)]
#[Small]
final class DiagramNodeTest extends TestCase
{
    public function testANodeRemembersWhatIdentifiesIt(): void
    {
        self::assertSame('App\Invoice', (new DiagramNode('App\Invoice'))->id);
    }

    public function testANodeRemembersWhatKindOfSymbolItIs(): void
    {
        self::assertSame('class', (new DiagramNode('App\Invoice', 'class'))->kind);
    }

    public function testANodeRemembersWhereItIsWritten(): void
    {
        self::assertSame('src/Invoice.php:12', (new DiagramNode('App\Invoice', 'class', 'src/Invoice.php:12'))->location);
    }

    public function testANodeKnowsNothingAboutASymbolNothingWasSaidAbout(): void
    {
        self::assertNull((new DiagramNode('App\Invoice'))->location);
    }
}
