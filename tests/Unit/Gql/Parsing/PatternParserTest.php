<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\DatumKind;
use App\Gql\Datum\FloatDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\NullDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\StatusCode;
use App\Gql\Syntax\Expression\BinaryExpression;
use App\Gql\Syntax\Expression\BinaryOperator;
use App\Gql\Syntax\Expression\LiteralExpression;
use App\Gql\Syntax\Expression\PropertyExpression;
use App\Gql\Syntax\Expression\VariableExpression;
use App\Gql\Syntax\Pattern\EdgeDirection;
use App\Gql\Syntax\Pattern\EdgePattern;
use App\Gql\Syntax\Pattern\ElementFilter;
use App\Gql\Syntax\Pattern\GraphPattern;
use App\Gql\Syntax\Pattern\GroupPattern;
use App\Gql\Syntax\Pattern\LabelOperator;
use App\Gql\Syntax\Pattern\LabelPattern;
use App\Gql\Syntax\Pattern\NodePattern;
use App\Gql\Syntax\Pattern\PathMode;
use App\Gql\Syntax\Pattern\PathPattern;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Gql\PatternSpelling;
use Tests\Fixture\Gql\QuerySpelling;

/**
 * @internal
 */
#[CoversClass(PatternParser::class)]
#[UsesClass(GqlException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Lexer::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenList::class)]
#[UsesClass(TokenReader::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(BooleanDatum::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(FloatDatum::class)]
#[UsesClass(IntegerDatum::class)]
#[UsesClass(NullDatum::class)]
#[UsesClass(StringDatum::class)]
#[UsesClass(BinaryExpression::class)]
#[UsesClass(BinaryOperator::class)]
#[UsesClass(LiteralExpression::class)]
#[UsesClass(PropertyExpression::class)]
#[UsesClass(VariableExpression::class)]
#[UsesClass(EdgeDirection::class)]
#[UsesClass(EdgePattern::class)]
#[UsesClass(ElementFilter::class)]
#[UsesClass(GraphPattern::class)]
#[UsesClass(GroupPattern::class)]
#[UsesClass(LabelOperator::class)]
#[UsesClass(LabelPattern::class)]
#[UsesClass(NodePattern::class)]
#[UsesClass(PathMode::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(Quantifier::class)]
#[UsesClass(LabelParser::class)]
#[Small]
final class PatternParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerPatternsAndTheirShape')]
    public function testParseGraphReadsEveryPathOneMatchLooksFor(string $written, string $shape): void
    {
        self::assertSame($shape, PatternSpelling::of($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPatternsAndTheirShape(): iterable
    {
        yield 'a node on its own' => ['(p)', '(p)'];

        yield 'two paths matched together' => ['(a), (b)', '(a), (b)'];

        yield 'an edge followed forwards' => ['(a)-[:calls]->(b)', '(a)-[:calls]->(b)'];

        yield 'an edge read backwards' => ['(a)<-[:calls]-(b)', '(a)<-[:calls]-(b)'];

        yield 'an edge crossed either way' => ['(a)-[:calls]-(b)', '(a)-[:calls]-(b)'];

        yield 'the shortcut for an edge of any kind' => ['()->()', '()-[]->()'];

        yield 'the shortcut read backwards' => ['()<-()', '()<-[]-()'];

        yield 'the shortcut crossed either way' => ['()-()', '()-[]-()'];

        yield 'a repetition' => ['(a)-[:calls]->{1,3}(b)', '(a)-[:calls]->{1,3}(b)'];

        yield 'a repetition with no lower bound' => ['(a)-[:calls]->{,3}(b)', '(a)-[:calls]->{0,3}(b)'];

        yield 'a repetition with no upper bound' => ['(a)-[:calls]->{1,}(b)', '(a)-[:calls]->{1,}(b)'];

        yield 'an exact repetition' => ['(a)-[:calls]->{3}(b)', '(a)-[:calls]->{3,3}(b)'];

        yield 'a parenthesised stretch of path' => ['((a)-[:x]->(b)){1,3}', '((a)-[:x]->(b)){1,3}'];

        yield 'a named path' => ['p = (a)', 'p=(a)'];

        yield 'a mode written before the path' => ['ACYCLIC (a)', 'ACYCLIC (a)'];

        yield 'a mode written before the name' => ['ACYCLIC p = (a)', 'p=ACYCLIC (a)'];

        yield 'a name written before the mode' => ['p = ACYCLIC (a)', 'p=ACYCLIC (a)'];

        yield 'a requirement on properties' => ["(p {kind: 'method'})", "(p{kind:'method'})"];

        yield 'a requirement written as a predicate' => ['(p WHERE p.line > 10)', '(p WHERE (p.line > 10))'];

        yield 'a requirement on an edge' => ['(a)-[e:calls WHERE e.line > 1]->(b)', '(a)-[e:calls WHERE (e.line > 1)]->(b)'];

        yield 'a requirement on the labels' => ['(p:Method|`Function`)', '(p:(Method|Function))'];
    }

    /**
     * @throws GqlException
     */
    public function testParsePathReadsOnePathWithTheNameAndModeItWasGiven(): void
    {
        self::assertSame('p=SIMPLE (a)', QuerySpelling::path(PatternSpelling::parser('SIMPLE p = (a)')->parsePath()));
    }

    /**
     * @throws GqlException
     */
    public function testParsePathNameReadsTheNameAPathIsGiven(): void
    {
        self::assertSame('p', PatternSpelling::parser('p = (a)')->parsePathName());
    }

    /**
     * @throws GqlException
     */
    public function testParsePathNameLeavesANameThatDoesNotNameAPath(): void
    {
        self::assertNull(PatternSpelling::parser('(a)')->parsePathName());
    }

    /**
     * @throws GqlException
     */
    public function testParseModeReadsTheModeWrittenBeforeAPath(): void
    {
        self::assertSame(PathMode::Simple, PatternSpelling::parser('SIMPLE (a)')->parseMode());
    }

    /**
     * @throws GqlException
     */
    public function testParseModeDefaultsToCrossingNoEdgeTwice(): void
    {
        self::assertSame(PathMode::Trail, PatternSpelling::parser('(a)')->parseMode());
    }

    /**
     * @throws GqlException
     */
    public function testParseModeLeavesAVariableThatHappensToSpellOne(): void
    {
        self::assertSame(PathMode::Trail, PatternSpelling::parser('SIMPLE = 1')->parseMode());
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsReadsAPathAsAnAlternationOfNodesAndEdges(): void
    {
        self::assertCount(3, PatternSpelling::parser('(a)-[:calls]->(b)')->parseTerms());
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeOrGroupReadsAParenthesisHoldingAParenthesisAsAGroup(): void
    {
        self::assertSame(
            GroupPattern::class,
            PatternSpelling::parser('((a)-[]->(b)){1,3}')->parseNodeOrGroup()::class,
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeOrGroupReadsAnythingElseAsANode(): void
    {
        self::assertSame(NodePattern::class, PatternSpelling::parser('(p:Method)')->parseNodeOrGroup()::class);
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeReadsWhatAPatternRequiresOfANode(): void
    {
        self::assertSame('p', PatternSpelling::parser('(p:Method)')->parseNode()->variable);
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeReadsAPatternThatRequiresNothing(): void
    {
        self::assertNull(PatternSpelling::parser('()')->parseNode()->variable);
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerArrowsAndTheDirectionTheyMean')]
    public function testParseEdgeReadsWhichWayThePatternCrossesTheEdge(string $written, EdgeDirection $direction): void
    {
        self::assertSame($direction, PatternSpelling::parser($written)->parseEdge()->direction);
    }

    /**
     * @return iterable<string, array{string, EdgeDirection}>
     */
    public static function providerArrowsAndTheDirectionTheyMean(): iterable
    {
        yield 'pointing forward' => ['-[:calls]->(b)', EdgeDirection::Along];

        yield 'pointing back' => ['<-[:calls]-(b)', EdgeDirection::Against];

        yield 'pointing neither way' => ['-[:calls]-(b)', EdgeDirection::Either];

        yield 'the shortcut pointing forward' => ['->(b)', EdgeDirection::Along];

        yield 'the shortcut pointing back' => ['<-(b)', EdgeDirection::Against];

        yield 'the shortcut pointing neither way' => ['-(b)', EdgeDirection::Either];
    }

    /**
     * @throws GqlException
     */
    public function testParseEdgeRequiresNothingOfAnEdgeWrittenAsAShortcut(): void
    {
        self::assertNull(PatternSpelling::parser('->(b)')->parseEdge()->labels);
    }

    /**
     * @throws GqlException
     */
    public function testParseElementNameReadsTheNameAMatchedElementIsBoundTo(): void
    {
        self::assertSame('p', PatternSpelling::parser('p:Method)')->parseElementName());
    }

    /**
     * @throws GqlException
     */
    public function testParseElementNameBindsNothingWhenThePatternStartsWithARequirement(): void
    {
        self::assertNull(PatternSpelling::parser(':Method)')->parseElementName());
    }

    /**
     * @throws GqlException
     */
    public function testParseElementNameBindsNothingWhenThePatternStartsWithAPredicate(): void
    {
        self::assertNull(PatternSpelling::parser('WHERE p.line > 10)')->parseElementName());
    }

    /**
     * @throws GqlException
     */
    public function testParseLabelsReadsTheRequirementWrittenAfterAColon(): void
    {
        self::assertSame('Method', PatternSpelling::parser(':Method)')->parseLabels()?->name);
    }

    /**
     * @throws GqlException
     */
    public function testParseLabelsRequiresNothingWhenNoColonIsWritten(): void
    {
        self::assertNull(PatternSpelling::parser(')')->parseLabels());
    }

    /**
     * @throws GqlException
     */
    public function testParseFilterReadsThePropertiesAPatternRequires(): void
    {
        self::assertSame(
            ['name'],
            array_keys(PatternSpelling::parser("{name: 'Invoice'})")->parseFilter()->properties),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseFilterReadsThePredicateAPatternRequires(): void
    {
        self::assertNotNull(PatternSpelling::parser('WHERE p.line > 10)')->parseFilter()->predicate);
    }

    /**
     * @throws GqlException
     */
    public function testParseFilterRequiresNothingBeyondTheLabelsWhenNothingElseIsWritten(): void
    {
        self::assertNull(PatternSpelling::parser(')')->parseFilter()->predicate);
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesReadsEveryPropertyWritten(): void
    {
        $properties = PatternSpelling::parser("{kind: 'method', visibility: 'public'}")->parseProperties();

        self::assertSame(['kind', 'visibility'], array_keys($properties));
    }

    /**
     * @throws GqlException
     */
    public function testParsePropertiesReadsAPatternThatWritesNone(): void
    {
        self::assertSame([], PatternSpelling::parser('{}')->parseProperties());
    }

    /**
     * @throws GqlException
     */
    public function testParseQuantifierReadsAnExactRepetition(): void
    {
        self::assertSame(3, PatternSpelling::parser('{3}')->parseQuantifier()?->most);
    }

    /**
     * @throws GqlException
     */
    public function testParseQuantifierReadsARepetitionWithNoUpperBound(): void
    {
        self::assertNull(PatternSpelling::parser('{1,}')->parseQuantifier()?->most);
    }

    /**
     * @throws GqlException
     */
    public function testParseQuantifierReadsARepetitionWithNoLowerBound(): void
    {
        self::assertSame(0, PatternSpelling::parser('{,3}')->parseQuantifier()?->least);
    }

    /**
     * @throws GqlException
     */
    public function testParseQuantifierReadsAPatternThatWritesNoRepetition(): void
    {
        self::assertNull(PatternSpelling::parser('(b)')->parseQuantifier());
    }
}
