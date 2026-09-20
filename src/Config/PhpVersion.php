<?php

declare(strict_types=1);

namespace App\Config;

use const PHP_VERSION_ID;

/**
 * The PHP version the analysed sources are read as.
 *
 * Which version a source is read as decides what it is allowed to say. A file that
 * names a method `match` is PHP 7 and not PHP 8; a file that declares an enum is
 * PHP 8.1 and not PHP 8.0. Reading either one as the other version does not produce
 * a worse graph, it produces no graph for that file at all, so the version is a
 * setting rather than an assumption: peq reads the sources as the version it was
 * told, and as the version it runs on only when it was told nothing.
 *
 * The range peq supports is the range its parser reads: PHP 5.6 to PHP 8.5. Not every
 * analyzer reads all of it — the one built on PHPStan is held to PHPStan's own lower
 * bound of PHP 7.1 — so which versions a given run can be asked for is a question for
 * AnalyzerKind, and this type is the range the product as a whole answers for.
 *
 * The version is held as PHP's own `PHP_VERSION_ID` number, which is what both the
 * analysis engine and the parser are configured with.
 */
final readonly class PhpVersion
{
    /**
     * The oldest version peq reads sources as.
     */
    public const int OLDEST_SUPPORTED = 50600;

    /**
     * The newest version peq reads sources as, as PHPStan's own upper bound.
     */
    public const int NEWEST_SUPPORTED = 80599;

    /**
     * The grammar of a written version: a major, and optionally a minor and a patch.
     */
    private const string VERSION_PATTERN = '/^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/';

    /**
     * @example The number a version carries is PHP's own version id
     *     (new \App\Config\PhpVersion(70100))->id // => 70100
     *
     * @param int $id The version, in PHP_VERSION_ID form
     */
    public function __construct(
        public int $id,
    ) {
        assert($id >= self::OLDEST_SUPPORTED, 'A version to analyse must be one peq reads sources as');
        assert($id <= self::NEWEST_SUPPORTED, 'A version to analyse must be one peq reads sources as');
    }

    /**
     * Reads a written version, if it names one peq can analyse.
     *
     * A version may be written as its major, its minor or its patch release —
     * `8`, `8.3` and `8.3.2` are all accepted — because a project states its PHP
     * version in whichever of those forms it happens to use.
     *
     * @param string $value The version as it was written
     *
     * @return null|self The version, or null when the text names none peq can analyse
     *
     * @example A written minor version is the version peq analyses
     *     \App\Config\PhpVersion::tryFromString('7.1')?->id // => 70100
     * @example A patch release is read as the version it belongs to
     *     \App\Config\PhpVersion::tryFromString('8.3.2')?->id // => 80302
     * @example A version older than any analyzer reads is not one to analyse
     *     \App\Config\PhpVersion::tryFromString('5.5') // => null
     * @example Text that spells no version at all names none either
     *     \App\Config\PhpVersion::tryFromString('latest') // => null
     */
    public static function tryFromString(string $value): ?self
    {
        $id = self::parse($value);
        if ($id === null || $id < self::OLDEST_SUPPORTED || $id > self::NEWEST_SUPPORTED) {
            return null;
        }

        return new self($id);
    }

    /**
     * Reads the number a written version spells, whether or not peq can analyse it.
     *
     * @param string $value The version as it was written
     *
     * @return null|int The version in PHP_VERSION_ID form, or null when the text spells none
     *
     * @example A version spells the number PHP spells it with
     *     \App\Config\PhpVersion::parse('8.3') // => 80300
     * @example A version peq cannot analyse still spells a number
     *     \App\Config\PhpVersion::parse('5.3') // => 50300
     */
    public static function parse(string $value): ?int
    {
        if (preg_match(self::VERSION_PATTERN, $value, $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 10000 + (int) ($parts[2] ?? 0) * 100 + (int) ($parts[3] ?? 0);
    }

    /**
     * The version peq itself runs on, as far as it reads sources as one.
     *
     * This is what peq reads sources as when nothing says otherwise. A runtime newer
     * than the newest version peq reads is reported as that newest version, because
     * the alternative is refusing to run on a PHP that peq itself supports. There is
     * no bound in the other direction: peq needs PHP 8.3 to run at all, which is long
     * past the oldest version it reads.
     *
     * @return self The version peq runs on, as far as it reads sources as one
     */
    public static function host(): self
    {
        return new self(min(PHP_VERSION_ID, self::NEWEST_SUPPORTED));
    }

    /**
     * The oldest version peq analyses.
     *
     * @return self The oldest supported version
     *
     * @example The oldest version peq analyses
     *     \App\Config\PhpVersion::oldest()->toString() // => '5.6'
     */
    public static function oldest(): self
    {
        return new self(self::OLDEST_SUPPORTED);
    }

    /**
     * The newest version peq analyses.
     *
     * @return self The newest supported version
     *
     * @example The newest version peq analyses
     *     \App\Config\PhpVersion::newest()->toString() // => '8.5'
     */
    public static function newest(): self
    {
        return new self(self::NEWEST_SUPPORTED);
    }

    /**
     * Writes the version the way a support policy states one.
     *
     * A policy is stated in minor versions — a project supports PHP 8.3, not PHP
     * 8.3.2 — and so is every version peq reports or is asked for. The patch release
     * a version was read from is kept in its number, which is what the analysis is
     * configured with.
     *
     * @return string The major and minor version as text
     *
     * @example A version reads as the minor it belongs to
     *     (new \App\Config\PhpVersion(80300))->toString() // => '8.3'
     * @example A patch release reads as its minor too
     *     (new \App\Config\PhpVersion(80302))->toString() // => '8.3'
     */
    public function toString(): string
    {
        return intdiv($this->id, 10000).'.'.intdiv($this->id, 100) % 100;
    }
}
