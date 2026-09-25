<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocContext;
use App\Analyzer\Declaration\PhpDoc\DocIndex;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocContext::class)]
#[UsesNamespace('App\Analyzer\Declaration\PhpDoc')]
#[Small]
final class DocContextTest extends TestCase
{
    public function testEnterNodeKeepsUndocumentedClassScopesWithoutIndexingEmptyComments(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse(<<<'PHP'
            <?php
            namespace App;
            use Model\Base;
            class Subject extends Base {
                function empty() {}
                /** @return self */
                function own() {}
                /** @return parent */
                function inherited() {}
            }
            /** @return Base */
            function outside() {}
            PHP) ?? [];
        $index = new DocIndex();
        $names = new NameResolver();
        (new NodeTraverser($names, new DocContext($index, $names)))->traverse($nodes);

        self::assertSame('App\Subject', $index->returned('App\Subject::own')?->objects());
        self::assertSame('Model\Base', $index->returned('App\Subject::inherited')?->objects());
        self::assertSame('Model\Base', $index->returned('App\outside')?->objects());
        self::assertSame(['app\subject::own', 'app\subject::inherited', 'app\outside'], array_keys($index->blocks));
    }

    public function testLeaveNodeRestoresTheEnclosingNamespaceAfterAClass(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php namespace App; /** @template T of Item */ class Subject {} /** @return T */ function run() {}') ?? [];
        $index = new DocIndex();
        $names = new NameResolver();
        (new NodeTraverser($names, new DocContext($index, $names)))->traverse($nodes);

        self::assertSame('App\T', $index->returned('App\run')?->objects());
    }

    public function testEnterNodeAndLeaveNodeKeepTemplateShadowingLexical(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse(<<<'PHP'
            <?php
            namespace App;
            use Model\Target as Item;
            /** @template T of Item */
            class Subject {
                /**
                 * @template T of Other
                 * @return T
                 */
                function first() {}
                /** @return T */
                function second() {}
                /** @var T */
                public $value;
            }
            PHP) ?? [];
        $index = new DocIndex();
        $names = new NameResolver();
        (new NodeTraverser($names, new DocContext($index, $names)))->traverse($nodes);

        self::assertSame('App\Other', $index->returned('App\Subject::first')?->objects());
        self::assertSame('Model\Target', $index->returned('App\Subject::second')?->objects());
        self::assertSame(['app\subject', 'app\subject::first', 'app\subject::second', 'app\subject::$value'], array_keys($index->blocks));
    }
}
