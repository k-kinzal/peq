<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use App\Analyzer\AnalysisFailedException;
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
    /**
     * @param array<string, string> $files The source of each file of the scenario, by file name
     *
     * @throws AnalysisFailedException If the reference engine cannot finish reading the scenario
     */
    #[DataProvider('providerCorpus')]
    #[Test]
    public function testBothEnginesDescribeTheSameGraph(string $scenario, array $files): void
    {
        $directory = sys_get_temp_dir().'/peq-corpus-'.md5(serialize($files));
        is_dir($directory) || mkdir($directory, 0o777, true);
        array_walk($files, static fn (string $source, string $file): false|int => file_put_contents($directory.'/'.$file, $source));

        $reference = GraphSnapshot::of((new PhpStanAnalyzer())->analyze($directory));
        $candidate = GraphSnapshot::of((new NativeAnalyzer())->analyze($directory));

        self::assertSame($reference->fingerprint(), $candidate->fingerprint(), $scenario.': '.$candidate->differenceFrom($reference)->describe());
    }

    /**
     * @param array<string, string> $files The source of each file of the scenario, by file name
     */
    #[DataProvider('providerCorpus')]
    #[Test]
    public function testTheScenarioDescribesSomething(string $scenario, array $files): void
    {
        $directory = sys_get_temp_dir().'/peq-corpus-'.md5(serialize($files));
        is_dir($directory) || mkdir($directory, 0o777, true);
        array_walk($files, static fn (string $source, string $file): false|int => file_put_contents($directory.'/'.$file, $source));

        self::assertNotSame([], GraphSnapshot::of((new NativeAnalyzer())->analyze($directory))->nodes, $scenario);
    }

    /**
     * @return Generator<string, array{string, array<string, string>}>
     */
    public static function providerCorpus(): Generator
    {
        foreach (EquivalenceCorpus::SCENARIOS as $scenario => $files) {
            yield $scenario => [$scenario, $files];
        }
    }
}
