<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer;

use App\Analyzer\PhpStanAnalyzer\ReparsedSource;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReparsedSource::class)]
#[Small]
final class ReparsedSourceTest extends TestCase
{
    public function testStatementsReadsTheTopLevelOfAFile(): void
    {
        self::assertNotEmpty((new ReparsedSource())->statements(dirname(__DIR__, 3).'/Fixture/Source/ClassDependency.php'));
    }

    public function testStatementsResolvesNamesToTheirFullForm(): void
    {
        $statements = (new ReparsedSource())->statements(dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php') ?? [];
        $namespaces = array_values(array_filter($statements, static fn (Stmt $statement): bool => $statement instanceof Namespace_));

        self::assertCount(1, $namespaces);
        self::assertSame('Tests\Fixture\Source', $namespaces[0]->name?->toString());
    }

    public function testStatementsReadsAFileOnlyOnce(): void
    {
        $source = new ReparsedSource();
        $file = dirname(__DIR__, 3).'/Fixture/Source/ClassDependency.php';

        self::assertSame($source->statements($file), $source->statements($file));
    }

    public function testStatementsReportsNothingForAFileThatCannotBeRead(): void
    {
        self::assertNull((new ReparsedSource())->statements(__DIR__.'/nonexistent.php'));
    }

    public function testStatementsRemembersThatAFileCouldNotBeRead(): void
    {
        $source = new ReparsedSource();

        self::assertNull($source->statements(__DIR__.'/nonexistent.php'));
        self::assertNull($source->statements(__DIR__.'/nonexistent.php'));
    }

    public function testMethodBodyReadsTheStatementsAMethodIsWrittenWith(): void
    {
        $body = (new ReparsedSource())->methodBody(
            dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php',
            'Tests\Fixture\Source\MethodBodyClass',
            'testMethod',
        );

        self::assertNotNull($body);
        self::assertInstanceOf(Return_::class, $body[count($body) - 1]);
    }

    public function testMethodBodyReportsNothingForAMethodTheClassDoesNotDeclare(): void
    {
        self::assertNull((new ReparsedSource())->methodBody(
            dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php',
            'Tests\Fixture\Source\MethodBodyClass',
            'absentMethod',
        ));
    }

    public function testMethodBodyReportsNothingForAClassTheFileDoesNotDeclare(): void
    {
        self::assertNull((new ReparsedSource())->methodBody(
            dirname(__DIR__, 3).'/Fixture/Source/MethodBody.php',
            'Tests\Fixture\Source\AbsentClass',
            'testMethod',
        ));
    }

    public function testMethodBodyReportsNothingForAFileThatCannotBeRead(): void
    {
        self::assertNull((new ReparsedSource())->methodBody(__DIR__.'/nonexistent.php', 'Any', 'any'));
    }
}
