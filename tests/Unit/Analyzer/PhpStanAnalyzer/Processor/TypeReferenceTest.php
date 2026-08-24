<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\PhpStanAnalyzer\Processor\TypeReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TypeReference::class)]
#[UsesClass(FileMeta::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[Small]
final class TypeReferenceTest extends TestCase
{
    public function testTheNamedClassLikeIsCarriedThroughUnchanged(): void
    {
        $node = new ClassNode(ClassNodeId::of('App\Domain\Money'), false);

        self::assertSame($node, (new TypeReference($node, new FileMeta('/project/src/Invoice.php', 4, 1)))->node);
    }

    public function testWhereTheNameIsWrittenIsCarriedThroughUnchanged(): void
    {
        $meta = new FileMeta('/project/src/Invoice.php', 4, 1);

        self::assertSame($meta, (new TypeReference(new ClassNode(ClassNodeId::of('App\Domain\Money'), false), $meta))->meta);
    }

    public function testTwoNamesOfOneWrittenTypeCarryTheirOwnPositions(): void
    {
        $first = new TypeReference(new ClassNode(ClassNodeId::of('App\Domain\Money'), false), new FileMeta('/project/src/Invoice.php', 4, 1));
        $second = new TypeReference(new ClassNode(ClassNodeId::of('App\Domain\Invoice'), false), new FileMeta('/project/src/Invoice.php', 9, 1));

        self::assertNotSame($first->meta->line, $second->meta->line);
    }
}
