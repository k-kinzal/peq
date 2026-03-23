<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\Collector\DependencyCollector;
use App\Analyzer\PhpStanAnalyzer\Collector\InClassMethodCollector;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ContainerFactory as PhpStanContainerFactory;
use PHPStan\PharAutoloader;
use Symfony\Component\Yaml\Yaml;

/**
 * Factory for creating PHPStan container with custom configuration.
 */
final class ContainerFactory
{
    private static bool $phpStanAutoloaderInitialized = false;

    /**
     * Creates a PHPStan container with the necessary configuration.
     *
     * @param string[] $files List of files to analyze
     *
     * @return Container The configured PHPStan container
     */
    public function create(array $files): Container
    {
        $this->ensurePhpStanAutoloader();

        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }
        $containerFactory = new PhpStanContainerFactory($cwd);

        // We need to create a configuration file that registers our collector.
        // PHPStan ContainerFactory expects a list of config files.
        // However, since we want to inject our own collector, we might need to do it dynamically
        // or ensure the config loads it.
        // Since we are running programmatically, we can try to add the collector to the container
        // or use a temporary config file.

        // A better approach for programmatic usage without a physical config file for the collector
        // is to rely on PHPStan's ability to accept config files.
        // But here we want to be self-contained.

        // Let's try to create a container and then see if we can register the collector.
        // PHPStan's container is compiled, so we can't easily add services at runtime unless we use a config file.

        // Strategy: Create a temporary directory and put the neon file inside it.
        // This avoids Nette DI trying to create a cache directory "inside" the config file path.

        $tempDir = sys_get_temp_dir().'/peq-phpstan-'.uniqid();
        mkdir($tempDir);
        $tempConfig = $tempDir.'/phpstan.neon';

        $content = [
            'services' => [
                [
                    'class' => DependencyCollector::class,
                    'tags' => ['phpstan.collector'],
                ],
                [
                    'class' => InClassMethodCollector::class,
                    'tags' => ['phpstan.collector'],
                ],
            ],
            'parameters' => [
                'customRulesetUsed' => true,
                'level' => 0, // Collectors work at all levels; level 0 skips unused type-checking rules
                'tmpDir' => $tempDir.'/tmp',
            ],
            'includes' => [],
        ];

        file_put_contents($tempConfig, $this->generateNeon($content));

        try {
            // create(string $tempDirectory, array $additionalConfigFiles, array $analysedPaths, ...)
            return $containerFactory->create($tempDir, [$tempConfig], $files);
        } catch (\Throwable $e) {
            echo 'ContainerFactory error: '.$e->getMessage()."\n";

            throw $e;
        } finally {
            // Recursive delete
            $this->deleteDirectory($tempDir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = scandir($dir);
        if ($files === false) {
            return;
        }
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            (is_dir("{$dir}/{$file}")) ? $this->deleteDirectory("{$dir}/{$file}") : unlink("{$dir}/{$file}");
        }
        rmdir($dir);
    }

    /**
     * Generates NEON format string from array.
     * Simple implementation to avoid dependency on symfony/yaml if not strictly needed,
     * but we have symfony/yaml in composer.json.
     *
     * @param array<string, mixed> $content
     */
    private function generateNeon(array $content): string
    {
        // We use symfony/yaml as it is available
        return Yaml::dump($content, 4);
    }

    /**
     * When running inside a PHAR, PHPStan's PharAutoloader constructs nested
     * phar://phar://... paths that PHP cannot resolve. This method extracts the
     * bundled phpstan.phar to a temp directory and registers its autoloader.
     */
    private function ensurePhpStanAutoloader(): void
    {
        if (self::$phpStanAutoloaderInitialized || \Phar::running() === '') {
            return;
        }
        self::$phpStanAutoloaderInitialized = true;

        $pharPhpstan = \Phar::running().'/vendor/phpstan/phpstan/phpstan.phar';
        if (!file_exists($pharPhpstan)) {
            throw new \RuntimeException('phpstan.phar not found inside the PHAR archive');
        }

        $peqPhar = \Phar::running(false);
        $cacheKey = md5($peqPhar.'@'.filemtime($peqPhar));
        $cacheDir = sys_get_temp_dir().'/peq-phpstan-'.$cacheKey;
        $extractedPhar = $cacheDir.'/phpstan.phar';

        if (!file_exists($extractedPhar)) {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0o777, true);
            }
            copy($pharPhpstan, $extractedPhar);
        }

        // Remove the broken PharAutoloader and register one from the extracted PHAR
        // @phpstan-ignore phpstanApi.classConstant
        if (class_exists(PharAutoloader::class, false)) {
            // @phpstan-ignore phpstanApi.classConstant
            spl_autoload_unregister([PharAutoloader::class, 'loadClass']);
        }

        require_once 'phar://'.$extractedPhar.'/vendor/autoload.php';
    }
}
