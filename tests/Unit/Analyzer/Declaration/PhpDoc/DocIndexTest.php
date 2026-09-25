<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocIndex;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use PhpParser\Node\Expr\Variable;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocIndex::class)]
#[UsesNamespace('App\Analyzer')]
#[Small]
final class DocIndexTest extends TestCase
{
    public function testTypesKeepsParametersAndReturnsSeparateAcrossRepeatedLookups(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse("<?php /**\n * @param Input \$value\n * @return Output\n */ function run(\$value) {}") ?? [];
        $index = new DocIndex();
        $index->read(array_values($nodes));
        $block = DocIndex::block($nodes[0]);

        self::assertSame('Input', $index->types($block, 'param')['value']->objects());
        self::assertSame('Output', $index->types($block, 'return')['']->objects());
        self::assertSame('Input', $index->types($block, 'param')['value']->objects());
        self::assertSame('Output', $index->types($block, 'return')['']->objects());
        self::assertSame([], $index->types($block, 'var'));
    }

    public function testReadReturnedAndAliasKeepImportedTypesInTheirOriginalNamespace(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse(<<<'PHP'
            <?php
            namespace Model {
                use Domain\Target as Item;
                /** @phpstan-type Row Item */
                class Schema {}
            }
            namespace Client {
                /** @phpstan-import-type Row from \Model\Schema as Local */
                class Subject {
                    /** @return Local */
                    function run() {}
                }
            }
            PHP) ?? [];
        $index = new DocIndex();
        $index->read(array_values($nodes));

        self::assertSame('Domain\Target', $index->returned('CLIENT\Subject::RUN')?->objects());
        self::assertNull($index->returned('missing'));
        self::assertNull($index->alias('Missing', $index->blocks['client\subject']->scope, []));
        self::assertNull($index->alias('Local', $index->blocks['client\subject']->scope, ['Client\Subject:Local']));
    }

    public function testReturnedFindsOnlyDocumentedFunctionResults(): void
    {
        $index = new DocIndex();
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php /** @return Result */ function make() {}') ?? [];
        $index->read(array_values($nodes));

        self::assertSame('Result', $index->returned('MAKE')?->objects());
        self::assertNull($index->returned('missing'));
    }

    public function testTypesReadsNamedParametersAndAliasStopsCycles(): void
    {
        $index = new DocIndex();
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php /** @phpstan-type Cycle Cycle */ class Subject { /** @param Cycle $value */ function run($value) {} }') ?? [];
        $index->read(array_values($nodes));

        self::assertSame(['value'], array_keys($index->types($index->blocks['subject::run'], 'param')));
        self::assertSame('', $index->alias('Cycle', $index->blocks['subject']->scope, [])?->objects());
    }

    public function testAliasDoesNotInventMissingImportedDefinitions(): void
    {
        $index = new DocIndex();
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php /** @phpstan-import-type Item from Missing */ class Subject {}') ?? [];
        $index->read(array_values($nodes));

        self::assertNull($index->alias('Item', $index->blocks['subject']->scope, []));
    }

    public function testKeyPreservesPropertyCasingOnly(): void
    {
        self::assertSame('app\subject::run', DocIndex::key('App\Subject::Run'));
        self::assertSame('app\subject::$Value', DocIndex::key('App\Subject::$Value'));
    }

    public function testBlockAndTypesRetainOnlyIndexedDocumentation(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php /** @param Item $value */ function run($value) {}') ?? [];
        $index = new DocIndex();
        $index->read(array_values($nodes));

        self::assertSame(['value'], array_keys($index->types(DocIndex::block($nodes[0]), 'param')));
        self::assertNull(DocIndex::block(new Variable('value')));
        self::assertSame([], $index->types(null, 'param'));
    }

    public function testPromotedDoesNotApplyConstructorParametersToOrdinaryProperties(): void
    {
        $index = new DocIndex();
        $nodes = (new ParserFactory())->createForHostVersion()->parse('<?php class Subject { /** @param Item $value */ function __construct($value) {} }') ?? [];
        $index->read(array_values($nodes));

        self::assertNull($index->promoted('Subject', 'value', new ClassHierarchy(new Graph())));
    }

    public function testMemberReadsMagicDeclarationsWithoutInventingGraphNodes(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse("<?php /**\n * @property-read Item \$value\n * @method Result make()\n */ class Subject {}") ?? [];
        $index = new DocIndex();
        $index->read(array_values($nodes));
        $hierarchy = new ClassHierarchy(new Graph());

        self::assertSame('Item', $index->member('Subject', 'value', false, $hierarchy)?->objects());
        self::assertSame('Result', $index->member('Subject', 'MAKE', true, $hierarchy)?->objects());
        self::assertNull($index->member('Subject', 'missing', true, $hierarchy));
    }
}
