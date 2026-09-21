<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\EquivalenceCorpus;

/**
 * @internal
 */
#[CoversClass(NativeAnalyzer::class)]
#[Large]
final class NativeAnalyzerCoverageContractTest extends TestCase
{
    #[Test]
    public function testTheCorpusHoldsASymbolOfEveryKindSourcesCanDeclare(): void
    {
        $graphs = array_map(static function (array $files): Graph {
            $directory = sys_get_temp_dir().'/peq-corpus-'.md5(serialize($files));
            is_dir($directory) || mkdir($directory, 0o777, true);
            array_walk($files, static fn (string $source, string $file): false|int => file_put_contents($directory.'/'.$file, $source));

            return (new NativeAnalyzer())->analyze($directory);
        }, EquivalenceCorpus::SCENARIOS);
        $corpus = array_reduce($graphs, static fn (Graph $merged, Graph $graph): Graph => $merged->merge($graph), new Graph());

        $expected = array_map(static fn (NodeKind $kind): string => $kind->value, array_values(array_filter(NodeKind::cases(), static fn (NodeKind $kind): bool => $kind !== NodeKind::Builtin)));
        $found = array_values(array_unique(array_map(static fn (Node $node): string => $node->kind()->value, $corpus->nodes())));
        sort($expected);
        sort($found);

        self::assertSame($expected, $found, 'A kind of symbol no scenario declares is one no scenario checks the two engines agree about. A builtin type is left out: it names nothing a codebase declares, so reading sources never produces one.');
    }

    #[Test]
    public function testTheCorpusHoldsARelationOfEveryKind(): void
    {
        $graphs = array_map(static function (array $files): Graph {
            $directory = sys_get_temp_dir().'/peq-corpus-'.md5(serialize($files));
            is_dir($directory) || mkdir($directory, 0o777, true);
            array_walk($files, static fn (string $source, string $file): false|int => file_put_contents($directory.'/'.$file, $source));

            return (new NativeAnalyzer())->analyze($directory);
        }, EquivalenceCorpus::SCENARIOS);
        $corpus = array_reduce($graphs, static fn (Graph $merged, Graph $graph): Graph => $merged->merge($graph), new Graph());

        $expected = array_map(static fn (EdgeKind $kind): string => $kind->value, EdgeKind::cases());
        $edges = array_merge(...array_map(static fn (Node $node): array => $corpus->edges($node->id()), $corpus->nodes()));
        $found = array_values(array_unique(array_map(static fn (Edge $edge): string => $edge->kind()->value, $edges)));
        sort($expected);
        sort($found);

        self::assertSame($expected, $found, 'A kind of relation no scenario writes is one no scenario checks the two engines agree about.');
    }
}
