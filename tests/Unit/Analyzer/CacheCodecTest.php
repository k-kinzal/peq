<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CacheCodec;
use App\Analyzer\CachedSyntax;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(CacheCodec::class)]
#[UsesClass(CachedSyntax::class)]
#[UsesClass(Graph::class)]
#[UsesClass(GraphSnapshot::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class CacheCodecTest extends TestCase
{
    public function testEncodeRoundTripPreservesTypedGraphValues(): void
    {
        $graph = new Graph();
        $graph->addNode(new ClassNode(ClassNodeId::of('Invoice'), true));

        $stream = tmpfile();
        self::assertNotFalse($stream);
        fwrite($stream, implode('', iterator_to_array(CacheCodec::encode($graph))));
        rewind($stream);
        $restored = CacheCodec::decode($stream);
        fclose($stream);

        self::assertInstanceOf(Graph::class, $restored);
        self::assertSame(['class Invoice resolved=yes at nowhere'], GraphSnapshot::of($restored)->nodes);
    }

    public function testDecodeCorruptPayloadIsACacheMiss(): void
    {
        $stream = tmpfile();
        self::assertNotFalse($stream);
        $encoded = implode('', iterator_to_array(CacheCodec::encode(new Graph())));
        fwrite($stream, substr($encoded, 0, -1));
        rewind($stream);

        self::assertNull(CacheCodec::decode($stream));
        ftruncate($stream, 0);
        rewind($stream);
        fwrite($stream, 'incomplete');
        rewind($stream);
        self::assertNull(CacheCodec::decode($stream));
        fclose($stream);
    }

    public function testDecodeClassesOutsideThePassiveAnalysisValuesAreRejected(): void
    {
        $stream = tmpfile();
        self::assertNotFalse($stream);
        fwrite($stream, implode('', iterator_to_array(CacheCodec::encode(new stdClass()))));
        rewind($stream);

        self::assertNull(CacheCodec::decode($stream));
        fclose($stream);
    }

    public function testBatchesSplitGraphsWithoutSerializingTheirDerivedIndexes(): void
    {
        $graph = new Graph();
        $graph->addNodes(array_map(static fn (int $i): ClassNode => new ClassNode(ClassNodeId::of('Class'.$i)), range(1, 600)));

        $batches = iterator_to_array(CacheCodec::batches($graph));

        self::assertSame([256, 256, 88], array_map(count(...), $batches));
        self::assertEquals($graph->nodes(), array_merge(...$batches));
    }

    public function testRestoreRejectsNonListsAndValuesOutsideThePassiveClasses(): void
    {
        self::assertNull(CacheCodec::restore('not serialized'));
        self::assertNull(CacheCodec::restore(serialize(new Graph())));
        self::assertNull(CacheCodec::restore(serialize([])));
        self::assertNull(CacheCodec::restore(serialize([1])));
        self::assertNull(CacheCodec::restore(serialize([new stdClass()])));
        self::assertNull(CacheCodec::restore(serialize(['named' => new Graph()])));
        self::assertEquals([new CachedSyntax(null)], CacheCodec::restore(serialize([new CachedSyntax(null)])));
    }

    public function testAppendRejectsAnEntryWithTheWrongShape(): void
    {
        self::assertNull(CacheCodec::append(null, [new Graph()], true));
        self::assertNull(CacheCodec::append(new Graph(), [new CachedSyntax(null)], true));
        self::assertNull(CacheCodec::append(new CachedSyntax(null), [new CachedSyntax(null)], false));
        self::assertNull(CacheCodec::append(null, [new Graph(), new Graph()], false));
        self::assertEquals(new CachedSyntax(null), CacheCodec::append(null, [new CachedSyntax(null)], false));
    }

    public function testDecodeRestoresEveryBatchAndRequiresTheFinalDigest(): void
    {
        $graph = new Graph();
        $graph->addNodes(array_map(static fn (int $i): ClassNode => new ClassNode(ClassNodeId::of('Class'.$i)), range(1, 600)));
        $stream = tmpfile();
        self::assertNotFalse($stream);
        $entry = implode('', iterator_to_array(CacheCodec::encode($graph)));
        fwrite($stream, $entry);
        rewind($stream);

        $restored = CacheCodec::decode($stream);

        self::assertInstanceOf(Graph::class, $restored);
        self::assertEquals(GraphSnapshot::of($graph), GraphSnapshot::of($restored));
        ftruncate($stream, strlen(substr($entry, 0, -67)));
        rewind($stream);
        self::assertNull(CacheCodec::decode($stream));
        fclose($stream);
    }

    public function testDecodeRejectsChangedPayloadsAndTrailingBytes(): void
    {
        $graph = new Graph();
        $graph->addNode(new ClassNode(ClassNodeId::of('Invoice')));
        $entry = implode('', iterator_to_array(CacheCodec::encode($graph)));
        $stream = tmpfile();
        self::assertNotFalse($stream);
        fwrite($stream, str_replace('Invoice', 'Changed', $entry));
        rewind($stream);

        self::assertNull(CacheCodec::decode($stream));
        rewind($stream);
        fwrite($stream, $entry.'extra');
        rewind($stream);
        self::assertNull(CacheCodec::decode($stream));
        fclose($stream);
    }

    public function testDecodeRejectsOversizedLengthsBeforeReadingThePayload(): void
    {
        $stream = tmpfile();
        self::assertNotFalse($stream);
        fwrite($stream, "graph\n999999999 ".str_repeat('0', 64)."\n");
        rewind($stream);

        self::assertNull(CacheCodec::decode($stream));
        fclose($stream);
    }

    public function testDecodeRejectsADeletedCompleteBatchEvenWithIntactRecordChecksums(): void
    {
        $graph = new Graph();
        $graph->addNodes(array_map(static fn (int $i): ClassNode => new ClassNode(ClassNodeId::of('Class'.$i)), range(1, 600)));
        $parts = iterator_to_array(CacheCodec::encode($graph));
        array_splice($parts, 1, 2);
        $stream = tmpfile();
        self::assertNotFalse($stream);
        fwrite($stream, implode('', $parts));
        rewind($stream);

        self::assertNull(CacheCodec::decode($stream));
        fclose($stream);
    }
}
