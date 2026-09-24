<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CacheCodec;
use App\Analyzer\CachedSyntax;
use App\Analyzer\CacheStorage;
use App\Analyzer\PhaseCache;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use App\Analyzer\SourceParser;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CachedSyntax::class)]
#[UsesClass(CacheStorage::class)]
#[UsesClass(CacheCodec::class)]
#[UsesClass(PhaseCache::class)]
#[UsesClass(SourceParser::class)]
#[UsesClass(WorkingDirectory::class)]
#[Small]
final class CachedSyntaxTest extends TestCase
{
    public function testReadUnchangedFileSkipsParsingAndRetainsResolvedNames(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $file = $directory->write('source.php', '<?php namespace Project; function run() {}');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        CachedSyntax::read($file, SourceParser::forVersion(80300), 80300, $cache);
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::never())->method('parse');

        $restored = CachedSyntax::read($file, $parser, 80300, $cache);
        $directory->delete();

        self::assertNotNull($restored->statements);
        $namespace = $restored->statements[0];
        self::assertInstanceOf(\PhpParser\Node\Stmt\Namespace_::class, $namespace);
        self::assertInstanceOf(Function_::class, $namespace->stmts[0]);
        self::assertSame('Project\run', $namespace->stmts[0]->namespacedName?->toString());
    }

    public function testReadSameSizeEditWithTheSameTimestampInvalidatesSyntax(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $file = $directory->write('source.php', '<?php function one() {}');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        touch($file, 1000000000);
        CachedSyntax::read($file, SourceParser::forVersion(80300), 80300, $cache);
        file_put_contents($file, '<?php function two() {}');
        touch($file, 1000000000);

        $restored = CachedSyntax::read($file, SourceParser::forVersion(80300), 80300, $cache);
        $directory->delete();

        self::assertNotNull($restored->statements);
        self::assertInstanceOf(Function_::class, $restored->statements[0]);
        self::assertSame('two', $restored->statements[0]->name->toString());
    }

    public function testParseTargetPhpVersionsUseIndependentSyntaxEntries(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $file = $directory->write('source.php', '<?php function legacy($s) { return $s{0}; }');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));

        $legacy = CachedSyntax::read($file, SourceParser::forVersion(70400), 70400, $cache);
        $modern = CachedSyntax::read($file, SourceParser::forVersion(80300), 80300, $cache);
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::never())->method('parse');
        $invalid = CachedSyntax::read($file, $parser, 80300, $cache);
        $directory->delete();

        self::assertNotNull($legacy->statements);
        self::assertNull($modern->statements);
        self::assertNull($invalid->statements);
    }

    public function testReadMissingSourcesAreNotParsed(): void
    {
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::never())->method('parse');

        self::assertNull(CachedSyntax::read(__DIR__.'/missing.php', $parser)->statements);
    }

    public function testParseResolvesNamesWithoutACache(): void
    {
        $syntax = CachedSyntax::parse('<?php function run() {}', SourceParser::forVersion(80300));

        self::assertNotNull($syntax->statements);
        self::assertInstanceOf(Function_::class, $syntax->statements[0]);
        self::assertSame('run', $syntax->statements[0]->namespacedName?->toString());
    }

    public function testReadFilesWithIdenticalContentsKeepIndependentEntries(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $first = $directory->write('first.php', '<?php function run() {}');
        $second = $directory->write('second.php', '<?php function run() {}');
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        CachedSyntax::read($first, SourceParser::forVersion(80300), 80300, $cache);
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::once())->method('parse')->willReturn([]);

        self::assertSame([], CachedSyntax::read($second, $parser, 80300, $cache)->statements);
        $directory->delete();
    }
}
