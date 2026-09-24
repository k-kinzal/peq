<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\AnalysisInputs;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AnalysisInputs::class)]
#[UsesClass(WorkingDirectory::class)]
#[Medium]
final class AnalysisInputsTest extends TestCase
{
    public function testFingerprintSelectionOptionsAndContentsContributeToTheFingerprint(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $file = $directory->write('source.php', '<?php function one() {}');
        touch($file, 1000000000);
        $first = AnalysisInputs::fingerprint([$file], [80300]);
        $repeated = AnalysisInputs::fingerprint([$file], [80300]);
        $differentVersion = AnalysisInputs::fingerprint([$file], [70400]);
        $differentSelection = AnalysisInputs::fingerprint([], [80300]);
        file_put_contents($file, '<?php function two() {}');
        touch($file, 1000000000);
        $edited = AnalysisInputs::fingerprint([$file], [80300]);
        $directory->delete();

        self::assertSame($first, $repeated);
        self::assertNotSame($first, $differentVersion);
        self::assertNotSame($first, $differentSelection);
        self::assertNotSame($first, $edited);
    }

    public function testFingerprintDistinguishesExternalReflectionFromFileAvailability(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $directory->write('selected.php', '<?php class Selected {}');
        $external = $directory->write('external.php', '<?php class External {}');
        WorkingDirectory::at($directory->path.'/vendor/composer')->write('autoload_psr4.php', '<?php return ["" => ['.var_export($directory->path, true).']];');
        $previous = getcwd();
        self::assertNotFalse($previous);
        chdir($directory->path);

        try {
            $native = AnalysisInputs::fingerprint([$directory->path.'/selected.php'], [], false);
            $reference = AnalysisInputs::fingerprint([$directory->path.'/selected.php'], [], true);
            file_put_contents($external, '<?php class External { function added() {} }');
            self::assertSame($native, AnalysisInputs::fingerprint([$directory->path.'/selected.php'], [], false));
            self::assertNotSame($reference, AnalysisInputs::fingerprint([$directory->path.'/selected.php'], [], true));
            unlink($external);
            self::assertNotSame($native, AnalysisInputs::fingerprint([$directory->path.'/selected.php'], [], false));
        } finally {
            chdir($previous);
            $directory->delete();
        }
    }

    public function testMinimalRootsDeduplicateNestedTreesAndResolveLinks(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        WorkingDirectory::at($directory->path.'/src');
        $resolved = realpath($directory->path);
        self::assertNotFalse($resolved);

        self::assertSame([$resolved], AnalysisInputs::minimalRoots([$directory->path.'/src', $directory->path, $directory->path, $directory->path.'/missing']));
        $directory->delete();
    }

    public function testRootsComposerMapsIncludeExternalUnloadedDependencyDirectories(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $outside = WorkingDirectory::at(sys_get_temp_dir().'/peq-external-'.uniqid());
        WorkingDirectory::at($directory->path.'/vendor/composer')->write('autoload_psr4.php', '<?php return ["External\\\" => ['.var_export($outside->path, true).']];');
        $resolved = realpath($outside->path);
        self::assertNotFalse($resolved);

        $roots = AnalysisInputs::roots($directory->path);
        $directory->delete();
        $outside->delete();

        self::assertContains($resolved, $roots);
    }

    public function testMappedFilesFingerprintSourcesWithoutAPhpExtension(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $source = $directory->write('external.inc', '<?php class External {}');
        WorkingDirectory::at($directory->path.'/vendor/composer')->write('autoload_classmap.php', '<?php return ["External" => '.var_export($source, true).'];');
        $previous = getcwd();
        self::assertNotFalse($previous);
        chdir($directory->path);

        try {
            self::assertContains($source, AnalysisInputs::mappedFiles($directory->path));
            $before = AnalysisInputs::fingerprint([], []);
            file_put_contents($source, '<?php class External { function added() {} }');
            self::assertNotSame($before, AnalysisInputs::fingerprint([], []));
        } finally {
            chdir($previous);
            $directory->delete();
        }
    }
}
