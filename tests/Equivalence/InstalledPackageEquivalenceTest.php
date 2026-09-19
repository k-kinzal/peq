<?php

declare(strict_types=1);

namespace Tests\Equivalence;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\InstalledPackages;

/**
 * @internal
 */
#[CoversNothing]
#[Large]
#[RunTestsInSeparateProcesses]
final class InstalledPackageEquivalenceTest extends TestCase
{
    /**
     * @param string $package The name of the installed package
     * @param string $path    The source tree it keeps its code in
     *
     * @throws AnalysisFailedException If the reference engine cannot finish reading the package
     */
    #[DataProvider('providerInstalledPackages')]
    public function testBothEnginesDescribeTheSameGraphOfRealCode(string $package, string $path): void
    {
        $reference = GraphSnapshot::of((new PhpStanAnalyzer())->analyze($path));
        $candidate = GraphSnapshot::of((new NativeAnalyzer())->analyze($path));

        self::assertNotSame([], $reference->nodes, $package.' gives the engines nothing to disagree about');
        self::assertSame($reference->fingerprint(), $candidate->fingerprint(), $package.': '.$candidate->differenceFrom($reference)->describe());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInstalledPackages(): iterable
    {
        foreach (InstalledPackages::sourceRoots(dirname(__DIR__, 2)) as $package => $path) {
            yield $package => [$package, $path];
        }
    }

    /**
     * Guards the sweep against reading nothing and reporting success.
     */
    public function testThereAreInstalledPackagesToRead(): void
    {
        self::assertNotSame([], InstalledPackages::sourceRoots(dirname(__DIR__, 2)), 'No installed package was read, so nothing about real code was checked.');
    }
}
