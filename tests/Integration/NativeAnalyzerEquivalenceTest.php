<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Graph\GraphDifference;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NativeAnalyzer::class)]
#[Large]
final class NativeAnalyzerEquivalenceTest extends TestCase
{
    #[DataProvider('providerRealSources')]
    public function testTheTwoEnginesDescribeTheSameGraph(string $label, GraphSnapshot $reference, GraphSnapshot $candidate): void
    {
        self::assertSame($reference->fingerprint(), $candidate->fingerprint(), $label.': '.$candidate->differenceFrom($reference)->describe());
    }

    #[DataProvider('providerRealSources')]
    public function testTheAnalysisFoundSomethingToCompare(string $label, GraphSnapshot $reference, GraphSnapshot $candidate): void
    {
        self::assertGreaterThan(20, count($candidate->nodes), $label);
        self::assertGreaterThan(20, count($reference->nodes), $label);
    }

    #[DataProvider('providerRealSources')]
    public function testTheDifferenceBetweenThemIsNothingAtAll(string $label, GraphSnapshot $reference, GraphSnapshot $candidate): void
    {
        self::assertEquals(new GraphDifference([], [], [], []), $candidate->differenceFrom($reference), $label);
    }

    /**
     * @return iterable<string, array{string, GraphSnapshot, GraphSnapshot}>
     */
    public static function providerRealSources(): iterable
    {
        foreach (['everything under src' => 'src', 'the sources written to exercise the analyzer' => 'tests/Fixture'] as $label => $directory) {
            $path = dirname(__DIR__, 2).'/'.$directory;

            yield $label => [
                $label,
                GraphSnapshot::of((new PhpStanAnalyzer())->analyze($path)),
                GraphSnapshot::of((new NativeAnalyzer())->analyze($path)),
            ];
        }
    }
}
