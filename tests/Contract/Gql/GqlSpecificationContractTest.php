<?php

declare(strict_types=1);

namespace Tests\Contract\Gql;

use App\Gql\GqlException;
use App\Gql\Lexing\TokenKind;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\Parser;
use App\Gql\Parsing\TokenReader;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\GqlSpecification;

/**
 * @internal
 */
#[CoversClass(Parser::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(TokenKind::class)]
#[Medium]
final class GqlSpecificationContractTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerQueriesTheDocumentationPrints')]
    #[Test]
    public function testEveryQueryTheDocumentationPrintsIsOnePeqReads(string $query): void
    {
        self::assertNotSame([], Parser::read($query)->blocks);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerQueriesTheDocumentationPrints(): Generator
    {
        foreach (GqlSpecification::queries() as $name => $query) {
            yield $name => [$query];
        }
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerExpressionsTheDocumentationPrints')]
    #[Test]
    public function testEveryExpressionTheDocumentationPrintsIsOnePeqReads(string $written): void
    {
        $tokens = TokenReader::of($written);
        (new ExpressionParser($tokens))->parse();

        self::assertSame(
            TokenKind::End,
            $tokens->current()->kind,
            sprintf('peq read only part of "%s".', $written),
        );
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerExpressionsTheDocumentationPrints(): Generator
    {
        foreach (GqlSpecification::expressions() as $name => $written) {
            yield $name => [$written];
        }
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerPatternsTheDocumentationPrints')]
    #[Test]
    public function testEveryPatternTheDocumentationPrintsIsOnePeqMatchesOn(string $written): void
    {
        self::assertNotSame([], Parser::read('MATCH '.$written)->blocks);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerPatternsTheDocumentationPrints(): Generator
    {
        foreach (GqlSpecification::patterns() as $name => $written) {
            yield $name => [$written];
        }
    }
}
