<?php

declare(strict_types=1);

namespace Tests\Contract\Gql;

use App\Gql\GqlException;
use App\Gql\Invocation\FunctionCatalog;
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
#[UsesClass(FunctionCatalog::class)]
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
        self::assertNotSame([], Parser::read('MATCH '.$written.' RETURN *')->blocks);
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

    /**
     * @throws GqlException
     */
    #[DataProvider('providerStatementsTheDocumentationPrints')]
    #[Test]
    public function testEveryStatementTheDocumentationPrintsIsOnePeqReads(string $written): void
    {
        self::assertNotSame([], Parser::read($written."\nRETURN *")->blocks);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerStatementsTheDocumentationPrints(): Generator
    {
        foreach (GqlSpecification::statements() as $name => $written) {
            yield $name => [$written];
        }
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerExtensionsTheDocumentationPrints')]
    #[Test]
    public function testNothingTheDocumentationAddsToGqlIsSomethingPeqReads(string $written): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('invalid syntax');

        Parser::read($written);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerExtensionsTheDocumentationPrints(): Generator
    {
        foreach (GqlSpecification::fabricExtensions() as $name => $written) {
            yield $name => [$written];
        }
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerFunctionsTheDocumentationAddsToGql')]
    #[Test]
    public function testNoFunctionTheDocumentationAddsToGqlIsOnePeqOffers(string $name): void
    {
        $this->expectException(GqlException::class);
        $this->expectExceptionMessage(sprintf('there is no function called "%s"', $name));

        FunctionCatalog::call($name, []);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function providerFunctionsTheDocumentationAddsToGql(): Generator
    {
        foreach (GqlSpecification::fabricFunctions() as $section => $name) {
            yield $section => [$name];
        }
    }
}
