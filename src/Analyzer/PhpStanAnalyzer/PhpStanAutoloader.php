<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use Phar;
use PHPStan\PharAutoloader;
use RuntimeException;

/**
 * Makes PHPStan's classes loadable when peq itself is running from a PHAR.
 *
 * PHPStan ships as a PHAR of its own, and peq bundles it. Loading a PHAR from inside
 * a PHAR gives PHPStan's own autoloader paths of the form `phar://phar://...`, which
 * the PHP stream wrapper cannot resolve. Extracting the bundled archive once and
 * registering the autoloader from the extracted copy is what makes the analyzer work
 * from a distributed binary at all.
 *
 * Outside a PHAR there is nothing to do: Composer's autoloader already resolves
 * PHPStan.
 *
 * @visibility namespace
 */
final class PhpStanAutoloader
{
    /**
     * Whether this instance has already done its work.
     */
    private bool $registered = false;

    /**
     * Registers a usable PHPStan autoloader if the current process needs one.
     *
     * @throws RuntimeException If peq is running from a PHAR that does not bundle PHPStan
     */
    public function ensureRegistered(): void
    {
        if ($this->registered || Phar::running() === '') {
            return;
        }
        $this->registered = true;

        $bundled = Phar::running().'/vendor/phpstan/phpstan/phpstan.phar';
        if (!file_exists($bundled)) {
            throw new RuntimeException('phpstan.phar not found inside the PHAR archive');
        }

        $extracted = $this->extract($bundled);

        if (class_exists(PharAutoloader::class, false)) {
            spl_autoload_unregister([PharAutoloader::class, 'loadClass']);
        }

        require_once 'phar://'.$extracted.'/vendor/autoload.php';
    }

    /**
     * Copies the bundled PHPStan archive out to a cache directory, once per build.
     *
     * The cache key covers the running binary and its modification time, so a new
     * build of peq extracts a fresh copy instead of reusing the previous one.
     *
     * @param string $bundled Path to the PHPStan archive inside the running PHAR
     *
     * @return string The path of the extracted archive
     */
    public function extract(string $bundled): string
    {
        $running = Phar::running(false);
        $cacheDirectory = sys_get_temp_dir().'/peq-phpstan-'.md5($running.'@'.filemtime($running));
        $extracted = $cacheDirectory.'/phpstan.phar';

        if (!file_exists($extracted)) {
            if (!is_dir($cacheDirectory)) {
                mkdir($cacheDirectory, 0o777, true);
            }
            copy($bundled, $extracted);
        }

        return $extracted;
    }
}
