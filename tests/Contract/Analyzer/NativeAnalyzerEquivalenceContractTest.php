<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
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
final class NativeAnalyzerEquivalenceContractTest extends TestCase
{
    #[DataProvider('providerCorpus')]
    #[Test]
    public function testBothEnginesDescribeTheSameGraph(string $scenario, string $path): void
    {
        $reference = GraphSnapshot::of((new PhpStanAnalyzer())->analyze($path));
        $candidate = GraphSnapshot::of((new NativeAnalyzer())->analyze($path));
        $difference = $candidate->differenceFrom($reference);

        self::assertSame($reference->fingerprint(), $candidate->fingerprint(), $scenario.': '.$difference->describe());
    }

    #[DataProvider('providerCorpus')]
    #[Test]
    public function testTheScenarioDescribesSomething(string $scenario, string $path): void
    {
        self::assertNotSame([], GraphSnapshot::of((new NativeAnalyzer())->analyze($path))->nodes, $scenario);
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function providerCorpus(): Generator
    {
        foreach (EquivalenceCorpus::scenarios() as $scenario => $files) {
            yield $scenario => [$scenario, EquivalenceCorpus::writeTo($scenario, $files)];
        }
    }
}
