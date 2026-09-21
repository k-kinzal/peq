<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Datum;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\NodeDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NodeDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[Small]
final class NodeDatumTest extends TestCase
{
    public function testASymbolCarriesWhatIdentifiesItAndWhatItIsLabelledWith(): void
    {
        $node = new NodeDatum('App\Invoice', ['Class', 'ClassLike']);

        self::assertSame('App\Invoice', $node->id);
        self::assertSame(['Class', 'ClassLike'], $node->labels);
    }

    public function testASymbolNeedNoLabelsOrPropertiesAtAll(): void
    {
        $node = new NodeDatum('App\Invoice');

        self::assertSame([], $node->labels);
        self::assertSame([], $node->properties);
    }

    public function testKindNamesASymbolOfTheGraph(): void
    {
        self::assertSame(DatumKind::Node, (new NodeDatum('a'))->kind());
    }

    public function testPropertyReadsAPropertyTheSymbolCarries(): void
    {
        $node = new NodeDatum('App\Invoice', [], ['name' => new StringDatum('Invoice')]);

        self::assertEquals(new StringDatum('Invoice'), $node->property('name'));
    }

    public function testPropertyReadsOneItDoesNotCarryAsAbsent(): void
    {
        self::assertEquals(new NullDatum(), (new NodeDatum('App\Invoice'))->property('visibility'));
    }

    public function testToTextShowsWhatIdentifiesTheSymbol(): void
    {
        self::assertSame('App\Domain\Invoice', (new NodeDatum('App\Domain\Invoice', ['Class']))->toText());
    }
}
