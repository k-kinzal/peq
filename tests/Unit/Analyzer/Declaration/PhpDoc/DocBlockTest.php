<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocBlock;
use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocBlock::class)]
#[UsesNamespace('App\Analyzer\Declaration\PhpDoc')]
#[Small]
final class DocBlockTest extends TestCase
{
    public function testTypesSelectsPriorityIndependentlyOfWrittenOrder(): void
    {
        $block = new DocBlock((new DocParser())->parse("/**\n * @phpstan-param Item \$value\n * @psalm-param Other \$value\n * @param object \$value\n * @param Item ...\$rest\n */"), new DocScope(new NameContext(new Collecting())));

        self::assertSame(['value' => 'Item', 'rest' => 'Item[]'], array_map(strval(...), $block->types('param')));
        self::assertSame([], $block->types('return'));
    }

    public function testTypesKeepsReadableMagicPropertiesSeparateFromWriteOnlyOnes(): void
    {
        $block = new DocBlock((new DocParser())->parse("/**\n * @property Base \$value\n * @property-read Readable \$value\n * @property-write Writable \$writeOnly\n * @method Result Create()\n */"), new DocScope(new NameContext(new Collecting())));

        self::assertSame(['value' => 'Readable'], array_map(strval(...), $block->types('property')));
        self::assertSame(['create' => 'Result'], array_map(strval(...), $block->types('method')));
    }
}
