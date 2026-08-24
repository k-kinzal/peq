<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use RuntimeException;

/**
 * A directory that exists only for the duration of one operation.
 *
 * PHPStan is configured through files on disk, so running it programmatically means
 * writing a configuration somewhere and taking it away again. Giving that its own
 * type keeps the creating, the writing and — most of all — the removing in one
 * place, instead of leaving a recursive delete sitting inside a factory that is
 * otherwise about building a container.
 *
 * @visibility namespace
 */
final class ScratchDirectory
{
    /**
     * @param string $path The absolute path of the directory
     */
    public function __construct(
        public readonly string $path,
    ) {}

    /**
     * Creates a new empty directory under the system temporary directory.
     *
     * @param string $prefix A prefix making the directory recognisable while it exists
     *
     * @return self The created directory
     *
     * @throws RuntimeException If the directory cannot be created
     */
    public static function create(string $prefix): self
    {
        $path = sys_get_temp_dir().'/'.$prefix.uniqid();
        if (!mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create the temporary directory "%s"', $path));
        }

        return new self($path);
    }

    /**
     * Writes a file into the directory.
     *
     * @param string $name     The file name, without a directory part
     * @param string $contents What to write into it
     *
     * @return string The absolute path of the written file
     *
     * @throws RuntimeException If the file cannot be written
     */
    public function write(string $name, string $contents): string
    {
        $file = $this->path.'/'.$name;
        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write the temporary file "%s"', $file));
        }

        return $file;
    }

    /**
     * Removes the directory and everything below it.
     *
     * Removal is best effort and never raises: it runs while an operation is being
     * unwound, including one that is already failing, and a directory left behind in
     * the system temporary directory must not replace the failure that caused it.
     */
    public function delete(): void
    {
        $entries = is_dir($this->path) ? scandir($this->path) : false;
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $this->path.'/'.$entry;
            if (is_dir($child)) {
                (new self($child))->delete();

                continue;
            }
            unlink($child);
        }

        rmdir($this->path);
    }
}
