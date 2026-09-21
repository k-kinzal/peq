<?php

declare(strict_types=1);

namespace App\Analyzer;

use const PHP_VERSION_ID;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

/**
 * The parser that reads analysed sources as the PHP version they are written for.
 *
 * Which version a source is read as decides what it is allowed to say: a method
 * named `match` is PHP 7 and not PHP 8, `$text{0}` is a string offset up to PHP 7.4
 * and a syntax error after it, an enum is PHP 8.1 and nothing earlier. A parser told
 * the wrong version does not read such a file badly — it does not read it at all.
 *
 * Both analyzers read the same files and are checked against each other, so how a
 * version becomes a parser is settled once, here, rather than once per engine.
 *
 * @visibility namespace
 *
 * @example The parser of a version reads what that version allows
 *     \App\Analyzer\SourceParser::forVersion(70100)->parse('<?php function f($s) { return $s{0}; }') !== null // => true
 * @example The parser of a version that dropped it does not
 *     \App\Analyzer\SourceParser::forVersion(80300)->parse('<?php function f($s) { return $s{0}; }') // throws \PhpParser\Error: Syntax error
 */
final readonly class SourceParser
{
    /**
     * Builds the parser that reads sources as the given PHP version.
     *
     * The version is a `PHP_VERSION_ID` number, of which only the major and the minor
     * decide anything: PHP's syntax changes in minor releases, never in patch ones.
     *
     * @param null|int $phpVersion The version the sources are read as, or null to read
     *                             them as the version peq itself runs on
     *
     * @return Parser The parser for that version
     *
     * @example A version the sources name is the version they are read as
     *     \App\Analyzer\SourceParser::forVersion(50600)->parse('<?php $copy =& new stdClass();') !== null // => true
     * @example A patch release is read as the minor version it belongs to
     *     \App\Analyzer\SourceParser::forVersion(50633)->parse('<?php $copy =& new stdClass();') !== null // => true
     * @example Reading as the running version is what happens when no version is named
     *     \App\Analyzer\SourceParser::forVersion(null)->parse('<?php $count = 1;') !== null // => true
     */
    public static function forVersion(?int $phpVersion): Parser
    {
        $version = $phpVersion ?? PHP_VERSION_ID;

        return (new ParserFactory())->createForVersion(PhpVersion::fromComponents(
            intdiv($version, 10000),
            intdiv($version, 100) % 100,
        ));
    }
}
