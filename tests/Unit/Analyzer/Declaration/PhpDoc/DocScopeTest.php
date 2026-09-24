<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocScope::class)]
#[UsesClass(DocParser::class)]
#[Small]
final class DocScopeTest extends TestCase
{
    public function testResolveRespectsImportsAndSpecialNames(): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace(new Name('App'));
        $names->addAlias(new Name('Domain\Item'), 'Alias', Use_::TYPE_NORMAL);
        $scope = new DocScope($names, 'App\Subject', 'App\Base');

        self::assertSame('Domain\Item', $scope->resolve('Alias'));
        self::assertSame('Domain\Item\Child', $scope->resolve('Alias\Child'));
        self::assertSame('App\Item', $scope->resolve('Item'));
        self::assertSame('Item', $scope->resolve('\Item'));
        self::assertSame('App\Subject', $scope->resolve('self'));
        self::assertSame('App\Subject', $scope->resolve('static'));
        self::assertSame('App\Subject', $scope->resolve('$this'));
        self::assertSame('App\Base', $scope->resolve('parent'));
    }

    public function testWithTypesKeepsLocalBindingsWithoutMutatingTheOuterScope(): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace();
        $outer = new DocScope($names);
        $inner = $outer->withTypes((new DocParser())->parse("/**\n * @template T of Item\n * @phpstan-type Row array{item: Item}\n * @phpstan-import-type Exported from Schema as Imported\n */"));

        self::assertSame([], $outer->localTypes);
        self::assertSame(['T', 'Row', 'Imported'], array_keys($inner->localTypes));
        self::assertSame('Item', (string) $inner->localTypes['T']);
        self::assertSame('Schema', (string) $inner->localTypes['Imported']);
        self::assertNull($inner->resolve('self'));
        self::assertNull($inner->resolve('parent'));
    }
}
