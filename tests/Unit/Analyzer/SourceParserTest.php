<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\SourceParser;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceParser::class)]
#[Small]
final class SourceParserTest extends TestCase
{
    public function testForVersionReadsSyntaxOnlyOlderVersionsAllow(): void
    {
        $errors = new Collecting();
        SourceParser::forVersion(50600)->parse('<?php $copy =& new stdClass();', $errors);

        self::assertFalse($errors->hasErrors());
    }

    public function testForVersionRefusesSyntaxTheVersionItWasGivenHadRemoved(): void
    {
        $errors = new Collecting();
        SourceParser::forVersion(80300)->parse('<?php $copy =& new stdClass();', $errors);

        self::assertTrue($errors->hasErrors());
    }

    public function testForVersionReadsSyntaxOnlyNewerVersionsAllow(): void
    {
        $errors = new Collecting();
        SourceParser::forVersion(80100)->parse('<?php enum Suit { case Hearts; }', $errors);

        self::assertFalse($errors->hasErrors());
    }

    public function testForVersionRefusesSyntaxTheVersionItWasGivenHadNotYetGained(): void
    {
        $errors = new Collecting();
        SourceParser::forVersion(80000)->parse('<?php enum Suit { case Hearts; }', $errors);

        self::assertTrue($errors->hasErrors());
    }

    public function testForVersionReadsAsTheRunningVersionWhenGivenNone(): void
    {
        $parsed = SourceParser::forVersion(null)->parse('<?php class Invoice {}');

        self::assertNotNull($parsed);
        self::assertInstanceOf(Class_::class, $parsed[0]);
    }

    #[DataProvider('providerPatchReleasesAndTheSyntaxTheirMinorAdmits')]
    public function testForVersionReadsAPatchReleaseAsTheMinorVersionItBelongsTo(int $phpVersion, string $code): void
    {
        $errors = new Collecting();
        SourceParser::forVersion($phpVersion)->parse($code, $errors);

        self::assertFalse($errors->hasErrors());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function providerPatchReleasesAndTheSyntaxTheirMinorAdmits(): iterable
    {
        yield 'a patch release of PHP 5.6 still reads a new by reference' => [50633, '<?php $copy =& new stdClass();'];

        yield 'a patch release of PHP 8.1 still reads an enum' => [80199, '<?php enum Suit { case Hearts; }'];
    }
}
