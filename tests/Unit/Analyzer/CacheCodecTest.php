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

        $restored = CacheCodec::decode(CacheCodec::encode($graph));

        self::assertInstanceOf(Graph::class, $restored);
        self::assertSame(['class Invoice resolved=yes at nowhere'], GraphSnapshot::of($restored)->nodes);
    }

    public function testDecodeCorruptPayloadIsACacheMiss(): void
    {
        $encoded = CacheCodec::encode(new Graph());

        self::assertNull(CacheCodec::decode(substr($encoded, 0, -1)));
        self::assertNull(CacheCodec::decode('incomplete'));
    }

    public function testDecodeClassesOutsideThePassiveAnalysisValuesAreRejected(): void
    {
        self::assertNull(CacheCodec::decode(CacheCodec::encode(new stdClass())));
    }
}
