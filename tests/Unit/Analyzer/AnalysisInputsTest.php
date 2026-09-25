<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\AnalysisInputs;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use Composer\Autoload\ClassLoader;
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

    public function testFingerprintWorkingDirectoriesAreIndependent(): void
    {
        $first = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $second = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $previous = getcwd();
        self::assertNotFalse($previous);

        try {
            chdir($first->path);
            $before = AnalysisInputs::fingerprint([], [], false);
            chdir($second->path);
            self::assertNotSame($before, AnalysisInputs::fingerprint([], [], false));
        } finally {
            chdir($previous);
            $first->delete();
            $second->delete();
        }
    }

    public function testFingerprintComposerManifestAndLockChangesInvalidateNativeAnalysis(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $manifest = $directory->write('composer.json', '{"autoload":{}}');
        $lock = $directory->write('composer.lock', '{"packages":[]}');
        $previous = getcwd();
        self::assertNotFalse($previous);
        chdir($directory->path);

        try {
            $before = AnalysisInputs::fingerprint([], [], false);
            file_put_contents($manifest, '{"autoload":{"files":["bootstrap.php"]}}');
            $manifestChanged = AnalysisInputs::fingerprint([], [], false);
            file_put_contents($lock, '{"packages":[{"name":"vendor/package"}]}');
            $lockChanged = AnalysisInputs::fingerprint([], [], false);
            self::assertNotSame($before, $manifestChanged);
            self::assertNotSame($manifestChanged, $lockChanged);
        } finally {
            chdir($previous);
            $directory->delete();
        }
    }

    public function testFingerprintSelectedContentsRemainTrackedWhenAlsoAutoloaded(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $source = $directory->write('selected.php', '<?php class Selected {}');
        WorkingDirectory::at($directory->path.'/vendor/composer')->write('autoload_classmap.php', '<?php return ["Selected" => '.var_export($source, true).'];');
        $previous = getcwd();
        self::assertNotFalse($previous);
        chdir($directory->path);

        try {
            $before = AnalysisInputs::fingerprint([$source], [], false);
            file_put_contents($source, '<?php class Selected { function added() {} }');
            self::assertNotSame($before, AnalysisInputs::fingerprint([$source], [], false));
        } finally {
            chdir($previous);
            $directory->delete();
        }
    }

    public function testFingerprintExternalRenamesAndPackageMetadataInvalidateNativeAnalysis(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $package = WorkingDirectory::at($directory->path.'/package');
        $source = $package->write('external.php', '<?php class External {}');
        $manifest = $package->write('composer.json', '{"autoload":{}}');
        WorkingDirectory::at($directory->path.'/vendor/composer')->write('autoload_psr4.php', '<?php return ["" => ['.var_export($package->path, true).']];');
        $previous = getcwd();
        self::assertNotFalse($previous);
        chdir($directory->path);

        try {
            $before = AnalysisInputs::fingerprint([], [], false);
            rename($source, $package->path.'/renamed.php');
            $renamed = AnalysisInputs::fingerprint([], [], false);
            file_put_contents($manifest, '{"autoload":{"files":["bootstrap.php"]}}');
            self::assertNotSame($before, $renamed);
            self::assertNotSame($renamed, AnalysisInputs::fingerprint([], [], false));
        } finally {
            chdir($previous);
            $directory->delete();
        }
    }

    public function testRootsIncludeTheUnregisteredProjectsComposerDirectory(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $composer = WorkingDirectory::at($directory->path.'/vendor/composer');
        $resolved = realpath($composer->path);
        self::assertNotFalse($resolved);

        $roots = AnalysisInputs::roots($directory->path);
        $directory->delete();

        self::assertContains($resolved, $roots);
    }

    public function testRootsIncludeEveryRegisteredAutoloadLocation(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $vendor = WorkingDirectory::at($directory->path.'/vendor');
        $psr4 = WorkingDirectory::at($directory->path.'/psr4');
        $psr0 = WorkingDirectory::at($directory->path.'/psr0');
        $fallback4 = WorkingDirectory::at($directory->path.'/fallback4');
        $fallback0 = WorkingDirectory::at($directory->path.'/fallback0');
        $mapped = WorkingDirectory::at($directory->path.'/mapped');
        $source = $mapped->write('external.inc', '<?php class External {}');
        $loader = new ClassLoader($vendor->path);
        $loader->addPsr4('External\\', $psr4->path);
        $loader->add('Legacy_', $psr0->path);
        $loader->addPsr4('', $fallback4->path);
        $loader->add('', $fallback0->path);
        $loader->addClassMap(['External' => $source]);
        $loader->register();

        try {
            $roots = AnalysisInputs::roots($directory->path);
            self::assertContains(realpath($vendor->path), $roots);
            self::assertContains(realpath($psr4->path), $roots);
            self::assertContains(realpath($psr0->path), $roots);
            self::assertContains(realpath($fallback4->path), $roots);
            self::assertContains(realpath($fallback0->path), $roots);
            self::assertContains(realpath($mapped->path), $roots);
        } finally {
            $loader->unregister();
            $directory->delete();
        }
    }

    public function testMappedFilesCombineRegisteredAndProjectMapsWithoutDuplicates(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $source = $directory->write('external.inc', '<?php class External {}');
        WorkingDirectory::at($directory->path.'/vendor/composer')->write('autoload_classmap.php', '<?php return ["External" => '.var_export($source, true).'];');
        $registered = $directory->write('registered.inc', '<?php class Registered {}');
        $loader = new ClassLoader($directory->path.'/vendor');
        $loader->addClassMap(['External' => $source, 'Registered' => $registered]);
        $loader->register();

        try {
            $files = AnalysisInputs::mappedFiles($directory->path);
            self::assertContains($registered, $files);
            self::assertSame([$source], array_values(array_filter($files, static fn (string $file): bool => $file === $source)));
        } finally {
            $loader->unregister();
            $directory->delete();
        }
    }

    public function testMinimalRootsRetainSiblingNamesThatShareAPrefix(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-inputs-'.uniqid());
        $first = WorkingDirectory::at($directory->path.'/src');
        $second = WorkingDirectory::at($directory->path.'/src-extra');

        self::assertSame([realpath($first->path), realpath($second->path)], AnalysisInputs::minimalRoots([$second->path, $first->path]));
        $directory->delete();
    }
}
