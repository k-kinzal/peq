<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Reads PHPStan's annotation grammar without loading its analysis engine.
 */
final readonly class DocParser
{
    private Lexer $lexer;
    private PhpDocParser $parser;

    /**
     * Prepares the PHPDoc lexer and parser with source positions enabled.
     */
    public function __construct()
    {
        $config = new ParserConfig(['lines' => true, 'indexes' => true]);
        $expressions = new ConstExprParser($config);
        $this->lexer = new Lexer($config);
        $this->parser = new PhpDocParser($config, new TypeParser($config, $expressions), $expressions);
    }

    /**
     * Malformed tags remain invalid AST nodes; valid neighbouring tags survive.
     */
    public function parse(string $comment): PhpDocNode
    {
        $tokens = $this->lexer->tokenize($comment);
        $doc = $this->parser->parse(new TokenIterator($tokens));
        $offsets = [];
        $offset = 0;
        foreach ($tokens as $token) {
            $offsets[] = $offset;
            $offset += strlen($token[Lexer::VALUE_OFFSET]);
        }
        foreach ($doc->getTags() as $tag) {
            $index = $tag->getAttribute('startIndex');
            $tag->setAttribute('peqOffset', is_int($index) ? ($offsets[$index] ?? null) : null);
        }

        return $doc;
    }
}
