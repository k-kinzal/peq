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
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('providerCallColumns')]
    public function testParseKeepsTheColumnOfEachReceiverCall(string $contents, int $line, int $column): void
    {
        $syntax = CachedSyntax::parse($contents, SourceParser::forVersion(80300));
        self::assertNotNull($syntax->statements);
        $call = (new \PhpParser\NodeFinder())->findFirst($syntax->statements, static fn (\PhpParser\Node $node): bool => $node instanceof \PhpParser\Node\Expr\MethodCall || $node instanceof \PhpParser\Node\Expr\NullsafeMethodCall);

        self::assertNotNull($call);
        self::assertSame($line, $call->getStartLine());
        self::assertSame($column, $call->getAttribute('peqStartColumn'));
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerCallColumns(): iterable
    {
        yield 'first line' => ['<?php $object->run();', 1, 7];

        yield 'first column' => ["<?php\n\$object->run();", 2, 1];

        yield 'indented after blank lines' => ["<?php\n\n    \$object->run();", 3, 5];

        yield 'CRLF and nullsafe call' => ["<?php\r\n    \$object?->run();", 2, 5];

        yield 'tab is one byte' => ["<?php\n\t\$object->run();", 2, 2];

        yield 'UTF-8 byte column' => ['<?php /* 日本語 */ $object->run();', 1, 23];

        yield 'multiline receiver' => ["<?php\n    \$object\n        ->run();", 2, 5];
    }

    public function testReadRetainsDistinctColumnsWhenSyntaxIsRestoredFromCache(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-cache-'.uniqid());
        $file = $directory->write('source.php', "<?php\n\$a->run(); \$b?->run();");
        $cache = new PhaseCache(new CacheStorage($directory->path.'/.peq.cache', 'v1'));
        CachedSyntax::read($file, SourceParser::forVersion(80300), 80300, $cache);
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::never())->method('parse');

        $restored = CachedSyntax::read($file, $parser, 80300, $cache);
        $directory->delete();
        self::assertNotNull($restored->statements);
        $calls = (new \PhpParser\NodeFinder())->find($restored->statements, static fn (\PhpParser\Node $node): bool => $node instanceof \PhpParser\Node\Expr\MethodCall || $node instanceof \PhpParser\Node\Expr\NullsafeMethodCall);

        self::assertCount(2, $calls);
        self::assertSame(1, $calls[0]->getAttribute('peqStartColumn'));
        self::assertSame(12, $calls[1]->getAttribute('peqStartColumn'));
    }

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
