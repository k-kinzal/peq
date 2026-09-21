<?php

declare(strict_types=1);

namespace Tests\Equivalence;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use FilesystemIterator;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Both engines over the source trees of the packages this checkout has installed.
 *
 * A corpus written by hand covers what its author thought of, and a codebase peq's
 * author did not write covers what nobody thought of: the constructs real libraries
 * reach for, in the proportions they reach for them. The dependencies already on
 * disk are such a codebase, and they cost nothing to obtain. They are not a fixed
 * list: a package that arrives tomorrow is read tomorrow.
 *
 * Reading all of them costs more memory than one process can hold, because the
 * reference engine keeps what it reflected over, so each package is read in a
 * process of its own.
 *
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
     * Finds the source tree of every installed package worth reading.
     *
     * A package is read at the directory it keeps its sources in rather than at its
     * root, so that its own tests and fixtures, which are written to be broken, are
     * not read as if they were library code. A package of fewer than eight files
     * exercises nothing a larger one does not, and each one read costs a whole run
     * of the reference engine.
     *
     * @return Generator<string, array{string, string}>
     */
    public static function providerInstalledPackages(): Generator
    {
        $vendor = dirname(__DIR__, 2).'/vendor';
        $packages = glob($vendor.'/*/*', GLOB_ONLYDIR);
        foreach ($packages === false ? [] : $packages as $package) {
            foreach (['src', 'lib', 'source'] as $directory) {
                $path = $package.'/'.$directory;
                if (!is_dir($path)) {
                    continue;
                }
                $files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)), '/\.php$/');
                if (iterator_count($files) < 8) {
                    continue;
                }

                $name = substr($package, strlen($vendor) + 1);
                $resolved = realpath($path);

                yield $name => [$name, $resolved === false ? $path : $resolved];

                break;
            }
        }
    }

    /**
     * Guards the sweep against reading nothing and reporting success.
     */
    public function testThereAreInstalledPackagesToRead(): void
    {
        self::assertNotSame([], iterator_to_array(self::providerInstalledPackages()), 'No installed package was read, so nothing about real code was checked.');
    }
}
