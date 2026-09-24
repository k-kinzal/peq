<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocParser;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocParser::class)]
#[Small]
final class DocParserTest extends TestCase
{
    public function testParseKeepsValidTagsAndTheirLocationsAfterMalformedTags(): void
    {
        $doc = (new DocParser())->parse("/**\n * @param array{broken \$input\n * @return list<Item>\n */");

        self::assertInstanceOf(InvalidTagValueNode::class, $doc->getTags()[0]->value);
        self::assertInstanceOf(ReturnTagValueNode::class, $doc->getTags()[1]->value);
        self::assertSame(3, $doc->getTags()[1]->getAttribute('startLine'));
        self::assertSame('list<Item>', (string) $doc->getReturnTagValues()[0]->type);
    }
}
