<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('providerSymbolKinds')]
    #[Test]
    public function testTheCorpusHoldsASymbolOfEveryKind(string $kind, bool $found): void
    {
        self::assertTrue($found, sprintf('No scenario declares a symbol of kind "%s", so no scenario checks the two engines agree about one.', $kind));
    }

    /**
     * @return Generator<string, array{string, bool}>
     */
    public static function providerSymbolKinds(): Generator
    {
        $found = [];
        foreach (EquivalenceCorpus::graph()->nodes() as $node) {
            $found[$node->kind()->value] = true;
        }

        foreach (NodeKind::cases() as $kind) {
            /**
             * A builtin type names nothing a codebase declares, so reading sources
             * never produces one: only the synthetic graphs of the debug analyzer do.
             */
            if ($kind === NodeKind::Builtin) {
                continue;
            }

            yield $kind->value => [$kind->value, isset($found[$kind->value])];
        }
    }

    #[DataProvider('providerRelationKinds')]
    #[Test]
    public function testTheCorpusHoldsARelationOfEveryKind(string $kind, bool $found): void
    {
        self::assertTrue($found, sprintf('No scenario writes a relation of kind "%s", so no scenario checks the two engines agree about one.', $kind));
    }

    /**
     * @return Generator<string, array{string, bool}>
     */
    public static function providerRelationKinds(): Generator
    {
        $graph = EquivalenceCorpus::graph();
        $found = [];
        foreach ($graph->nodes() as $node) {
            foreach ($graph->edges($node->id()) as $edge) {
                $found[$edge->kind()->value] = true;
            }
        }

        foreach (EdgeKind::cases() as $kind) {
            yield $kind->value => [$kind->value, isset($found[$kind->value])];
        }
    }
}
