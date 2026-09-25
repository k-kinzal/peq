<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocDependencies;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use org\bovigo\vfs\vfsStream;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocDependencies::class)]
#[UsesNamespace('App\Analyzer')]
#[Small]
final class DocDependenciesTest extends TestCase
{
    public function testEnrichKeepsValidDocumentedSourcesWhenOtherFilesCannotBeRead(): void
    {
        vfsStream::setup('docs', null, [
            'valid.php' => '<?php /** @return Item */ function run() {}',
            'invalid.php' => '<?php /** @return Ghost */ function broken(',
            'empty.php' => '<?php function plain() {}',
        ]);
        $graph = new Graph();
        $graph->addNode(new FunctionNode(FunctionNodeId::of('run'), true));

        $result = DocDependencies::enrich($graph, ['vfs://docs/missing.php', 'vfs://docs/invalid.php', 'vfs://docs/empty.php', 'vfs://docs/valid.php'], (new ParserFactory())->createForNewestSupportedVersion());

        self::assertSame($graph, $result);
        self::assertSame(['Item'], array_map(static fn (Edge $edge): string => $edge->to()->toString(), $result->forwardEdges()));
    }
}
