<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Parsing;

use App\Gql\Datum\DatumKind;
use App\Gql\Datum\DecimalDatum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\StringDatum;
use App\Gql\GqlException;
use App\Gql\Lexing\Lexer;
use App\Gql\Lexing\QuotedScanner;
use App\Gql\Lexing\SourceCursor;
use App\Gql\Lexing\Token;
use App\Gql\Lexing\TokenKind;
use App\Gql\Lexing\TokenList;
use App\Gql\Parsing\ElementParser;
use App\Gql\Parsing\ExpressionParser;
use App\Gql\Parsing\LabelParser;
use App\Gql\Parsing\NameReader;
use App\Gql\Parsing\OperandParser;
use App\Gql\Parsing\PatternParser;
use App\Gql\Parsing\QuantifierParser;
use App\Gql\Parsing\TokenReader;
use App\Gql\ReservedWords;
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
use App\Gql\Syntax\Pattern\PathTerm;
use App\Gql\Syntax\Pattern\Quantifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

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
#[UsesClass(NameReader::class)]
#[UsesClass(ElementParser::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(LabelParser::class)]
#[UsesClass(OperandParser::class)]
#[UsesClass(QuantifierParser::class)]
#[UsesClass(ReservedWords::class)]
#[UsesClass(DatumKind::class)]
#[UsesClass(DecimalDatum::class)]
#[UsesClass(IntegerDatum::class)]
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
#[Small]
final class PatternParserTest extends TestCase
{
    /**
     * @throws GqlException
     */
    #[DataProvider('providerPatternsAndWhatTheyDraw')]
    public function testParseGraphReadsEveryPathOneMatchLooksFor(string $written, GraphPattern $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new PatternParser($tokens, new ExpressionParser($tokens)))->parseGraph());
    }

    /**
     * @return iterable<string, array{string, GraphPattern}>
     */
    public static function providerPatternsAndWhatTheyDraw(): iterable
    {
        yield 'a node on its own, in a walk' => [
            '(p)',
            new GraphPattern([new PathPattern([new NodePattern('p')], PathMode::Walk)]),
        ];

        yield 'two paths matched together' => [
            '(a), (b)',
            new GraphPattern([
                new PathPattern([new NodePattern('a')], PathMode::Walk),
                new PathPattern([new NodePattern('b')], PathMode::Walk),
            ]),
        ];

        yield 'an edge followed forwards' => [
            '(a)-[:calls]->(b)',
            new GraphPattern([new PathPattern(
                [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('calls')), new NodePattern('b')],
                PathMode::Walk,
            )]),
        ];

        yield 'two edges in a row' => [
            '(a)-[:calls]->(b)<-[:calls]-(c)',
            new GraphPattern([new PathPattern(
                [
                    new NodePattern('a'),
                    new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('calls')),
                    new NodePattern('b'),
                    new EdgePattern(EdgeDirection::Against, null, LabelPattern::named('calls')),
                    new NodePattern('c'),
                ],
                PathMode::Walk,
            )]),
        ];

        yield 'the shortcut for an edge that requires nothing' => [
            '()->()',
            new GraphPattern([new PathPattern(
                [new NodePattern(), new EdgePattern(EdgeDirection::Along), new NodePattern()],
                PathMode::Walk,
            )]),
        ];

        yield 'a bounded repetition in a walk' => [
            '(a)-[:calls]->{1,3}(b)',
            new GraphPattern([new PathPattern(
                [
                    new NodePattern('a'),
                    new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('calls'), new ElementFilter(), new Quantifier(1, 3)),
                    new NodePattern('b'),
                ],
                PathMode::Walk,
            )]),
        ];

        yield 'an unbounded repetition under a restrictor' => [
            'TRAIL (a)-[:calls]->{1,}(b)',
            new GraphPattern([new PathPattern(
                [
                    new NodePattern('a'),
                    new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('calls'), new ElementFilter(), new Quantifier(1, null)),
                    new NodePattern('b'),
                ],
                PathMode::Trail,
            )]),
        ];

        yield 'a requirement on the properties of a node' => [
            "(p {kind: 'method'})",
            new GraphPattern([new PathPattern(
                [new NodePattern('p', null, new ElementFilter(['kind' => new LiteralExpression(new StringDatum('method'))]))],
                PathMode::Walk,
            )]),
        ];

        yield 'a requirement written as a predicate on an edge' => [
            '(a)-[e:calls WHERE e.line > 1]->(b)',
            new GraphPattern([new PathPattern(
                [
                    new NodePattern('a'),
                    new EdgePattern(
                        EdgeDirection::Along,
                        'e',
                        LabelPattern::named('calls'),
                        new ElementFilter([], new BinaryExpression(
                            BinaryOperator::Greater,
                            new PropertyExpression(new VariableExpression('e'), 'line'),
                            new LiteralExpression(new IntegerDatum(1)),
                        )),
                    ),
                    new NodePattern('b'),
                ],
                PathMode::Walk,
            )]),
        ];

        yield 'a parenthesised stretch of path, repeated' => [
            '((a)-[:x]->(b)){1,3}',
            new GraphPattern([new PathPattern(
                [new GroupPattern(
                    [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('x')), new NodePattern('b')],
                    new Quantifier(1, 3),
                )],
                PathMode::Walk,
            )]),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseGraphRefusesAnUnboundedRepetitionInAPathThatNamesNoRestrictor(): void
    {
        $tokens = TokenReader::of('(a)-[:calls]->*(b)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('write TRAIL, SIMPLE or ACYCLIC at line 1, column 15 (found "*")');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseGraph();
    }

    /**
     * @throws GqlException
     */
    public function testParseGraphRestrictsEachPathByItsOwnMode(): void
    {
        $tokens = TokenReader::of('TRAIL (a)-[]->*(b), (c)-[]->*(d)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('write TRAIL, SIMPLE or ACYCLIC at line 1, column 29 (found "*")');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseGraph();
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerPathsWithANameAndAMode')]
    public function testParsePathReadsOnePathWithTheNameAndModeItWasGiven(string $written, PathPattern $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new PatternParser($tokens, new ExpressionParser($tokens)))->parsePath());
    }

    /**
     * @return iterable<string, array{string, PathPattern}>
     */
    public static function providerPathsWithANameAndAMode(): iterable
    {
        yield 'neither, which makes it a walk' => ['(a)', new PathPattern([new NodePattern('a')], PathMode::Walk)];

        yield 'a name' => ['p = (a)', new PathPattern([new NodePattern('a')], PathMode::Walk, 'p')];

        yield 'a mode' => ['ACYCLIC (a)', new PathPattern([new NodePattern('a')], PathMode::Acyclic)];

        yield 'a mode written before the name' => ['SIMPLE p = (a)', new PathPattern([new NodePattern('a')], PathMode::Simple, 'p')];

        yield 'a name written before the mode' => ['p = SIMPLE (a)', new PathPattern([new NodePattern('a')], PathMode::Simple, 'p')];

        yield 'a walk asked for by name' => ['WALK (a)', new PathPattern([new NodePattern('a')], PathMode::Walk)];

        yield 'a name that spells a mode' => ['TRAIL = (a)', new PathPattern([new NodePattern('a')], PathMode::Walk, 'TRAIL')];
    }

    /**
     * @throws GqlException
     */
    public function testParsePathNameReadsTheNameAPathIsGiven(): void
    {
        $tokens = TokenReader::of('p = (a)');

        self::assertSame('p', (new PatternParser($tokens, new ExpressionParser($tokens)))->parsePathName());
        self::assertEquals(new Token(TokenKind::Symbol, '(', '(', 1, 5, 4), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerPathsThatAreNotNamedHere')]
    public function testParsePathNameLeavesWhatDoesNotNameAPath(string $written): void
    {
        $tokens = TokenReader::of($written);

        self::assertNull((new PatternParser($tokens, new ExpressionParser($tokens)))->parsePathName());
        self::assertSame(0, $tokens->position());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPathsThatAreNotNamedHere(): iterable
    {
        yield 'a node' => ['(a)'];

        yield 'a mode' => ['TRAIL (a)'];

        yield 'a name with no equals sign after it' => ['p (a)'];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerModes')]
    public function testParseModeReadsTheModeWrittenBeforeAPath(string $written, PathMode $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertSame($expected, (new PatternParser($tokens, new ExpressionParser($tokens)))->parseMode());
    }

    /**
     * @return iterable<string, array{string, PathMode}>
     */
    public static function providerModes(): iterable
    {
        yield 'none, which is a walk' => ['(a)', PathMode::Walk];

        yield 'a walk' => ['WALK (a)', PathMode::Walk];

        yield 'a trail' => ['TRAIL (a)', PathMode::Trail];

        yield 'a trail, in lower case' => ['trail (a)', PathMode::Trail];

        yield 'a simple path' => ['SIMPLE (a)', PathMode::Simple];

        yield 'an acyclic path' => ['ACYCLIC (a)', PathMode::Acyclic];
    }

    /**
     * @throws GqlException
     */
    public function testParseModeLeavesAVariableThatHappensToSpellOne(): void
    {
        $tokens = TokenReader::of('SIMPLE = (a)');

        self::assertSame(PathMode::Walk, (new PatternParser($tokens, new ExpressionParser($tokens)))->parseMode());
        self::assertSame(0, $tokens->position());
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsReadsAPathAsAnAlternationOfNodesAndEdges(): void
    {
        $tokens = TokenReader::of('(a)-[:calls]->(b)');

        self::assertEquals(
            [new NodePattern('a'), new EdgePattern(EdgeDirection::Along, null, LabelPattern::named('calls')), new NodePattern('b')],
            (new PatternParser($tokens, new ExpressionParser($tokens)))->parseTerms(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsReadsTwoNodePatternsSideBySideAsAConcatenation(): void
    {
        $tokens = TokenReader::of('(a) (b)');

        self::assertEquals([new NodePattern('a'), new NodePattern('b')], (new PatternParser($tokens, new ExpressionParser($tokens)))->parseTerms());
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsReadsANodePatternFollowedByARepeatedGroup(): void
    {
        $tokens = TokenReader::of('(s)((a)-[]->(b)){2}');

        self::assertEquals(
            [
                new NodePattern('s'),
                new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], Quantifier::exactly(2)),
            ],
            (new PatternParser($tokens, new ExpressionParser($tokens)))->parseTerms(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsPutsAnAnonymousNodePatternWhereAnEdgePatternHasNone(): void
    {
        $tokens = TokenReader::of('-[e]->-[f]->');

        self::assertEquals(
            [new NodePattern(), new EdgePattern(EdgeDirection::Along, 'e'), new NodePattern(), new EdgePattern(EdgeDirection::Along, 'f'), new NodePattern()],
            (new PatternParser($tokens, new ExpressionParser($tokens)))->parseTerms(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsStopsWhereNothingOfAPathFollows(): void
    {
        $tokens = TokenReader::of('(a), (b)');

        self::assertEquals([new NodePattern('a')], (new PatternParser($tokens, new ExpressionParser($tokens)))->parseTerms());
        self::assertEquals(new Token(TokenKind::Symbol, ',', ',', 1, 4, 3), $tokens->current());
    }

    /**
     * @throws GqlException
     */
    public function testParseTermsRefusesSomethingThatIsNoPathAtAll(): void
    {
        $tokens = TokenReader::of('RETURN');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected a path pattern');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseTerms();
    }

    /**
     * @throws GqlException
     */
    public function testAtEdgeFindsAnArrowStartingHere(): void
    {
        $tokens = TokenReader::of('<-(a)');

        self::assertTrue((new PatternParser($tokens, new ExpressionParser($tokens)))->atEdge());
    }

    /**
     * @throws GqlException
     */
    public function testAtEdgeFindsNoArrowAtAParenthesis(): void
    {
        $tokens = TokenReader::of('(a)');

        self::assertFalse((new PatternParser($tokens, new ExpressionParser($tokens)))->atEdge());
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerNodesAndGroups')]
    public function testParseNodeOrGroupTellsANodeFromAGroupByWhatFollowsTheParenthesis(string $written, PathTerm $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new PatternParser($tokens, new ExpressionParser($tokens)))->parseNodeOrGroup());
    }

    /**
     * @return iterable<string, array{string, PathTerm}>
     */
    public static function providerNodesAndGroups(): iterable
    {
        yield 'a parenthesis holding a parenthesis, repeated' => [
            '((a)-[]->(b)){1,3}',
            new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], new Quantifier(1, 3)),
        ];

        yield 'a parenthesis holding a parenthesis, matched once' => [
            '((a)-[]->(b))',
            new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')]),
        ];

        yield 'a group that crosses no relation, repeated a bounded number of times' => [
            '((a)){1,3}',
            new GroupPattern([new NodePattern('a')], new Quantifier(1, 3)),
        ];

        yield 'a parenthesis holding anything else' => ['(p:Method)', new NodePattern('p', LabelPattern::named('Method'))];
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeOrGroupReadsAGroupRepeatedWithoutEndUnderARestrictor(): void
    {
        $tokens = TokenReader::of('TRAIL ((a)-[]->(b))+');

        self::assertEquals(
            new PathPattern(
                [new GroupPattern([new NodePattern('a'), new EdgePattern(EdgeDirection::Along), new NodePattern('b')], new Quantifier(1, null))],
                PathMode::Trail,
            ),
            (new PatternParser($tokens, new ExpressionParser($tokens)))->parsePath(),
        );
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeOrGroupRefusesAGroupThatRepeatsWithoutEndAndCrossesNoRelation(): void
    {
        $tokens = TokenReader::of('TRAIL ((a))*');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('[42000] error: syntax error or access rule violation: a group that crosses no relation repeats without end at line 1, column 12, and peq repeats only what makes progress');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parsePath();
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeOrGroupRefusesAGroupRepeatedWithoutEndInAWalk(): void
    {
        $tokens = TokenReader::of('((a)-[]->(b))*');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('write TRAIL, SIMPLE or ACYCLIC at line 1, column 14 (found "*")');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseNodeOrGroup();
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerNodePatterns')]
    public function testParseNodeReadsWhatAPatternRequiresOfANode(string $written, NodePattern $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new PatternParser($tokens, new ExpressionParser($tokens)))->parseNode());
    }

    /**
     * @return iterable<string, array{string, NodePattern}>
     */
    public static function providerNodePatterns(): iterable
    {
        yield 'nothing at all' => ['()', new NodePattern()];

        yield 'a name and a label after a colon' => ['(p:Method)', new NodePattern('p', LabelPattern::named('Method'))];

        yield 'a name and a label after IS' => ['(p IS Method)', new NodePattern('p', LabelPattern::named('Method'))];

        yield 'a label GQL reserves the name of, in double quotes' => ['(:"Function")', new NodePattern(null, LabelPattern::named('Function'))];

        yield 'a property' => [
            "(p {kind: 'method'})",
            new NodePattern('p', null, new ElementFilter(['kind' => new LiteralExpression(new StringDatum('method'))])),
        ];

        yield 'a predicate' => [
            '(p WHERE p.line > 10)',
            new NodePattern('p', null, new ElementFilter([], new BinaryExpression(
                BinaryOperator::Greater,
                new PropertyExpression(new VariableExpression('p'), 'line'),
                new LiteralExpression(new IntegerDatum(10)),
            ))),
        ];
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeRefusesPropertiesAndAPredicateTogether(): void
    {
        $tokens = TokenReader::of("(p {kind: 'method'} WHERE p.line > 1)");

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected ")"');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseNode();
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeRefusesALabelGqlReservesTheNameOf(): void
    {
        $tokens = TokenReader::of('(:Function)');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('GQL reserves "FUNCTION", so it is not a name here: write it in back quotes to use it as a name');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseNode();
    }

    /**
     * @throws GqlException
     */
    public function testParseNodeReportsANodeThatIsNeverClosed(): void
    {
        $tokens = TokenReader::of('(p');

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage('expected ")" at line 1, column 3 (found the end of the query)');

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseNode();
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerEdgePatterns')]
    public function testParseEdgeReadsEachOfTheEdgePatternsGqlWrites(string $written, EdgePattern $expected): void
    {
        $tokens = TokenReader::of($written);

        self::assertEquals($expected, (new PatternParser($tokens, new ExpressionParser($tokens)))->parseEdge());
    }

    /**
     * @return iterable<string, array{string, EdgePattern}>
     */
    public static function providerEdgePatterns(): iterable
    {
        yield 'a directed edge read backwards' => ['<-[e:x]-', new EdgePattern(EdgeDirection::Against, 'e', LabelPattern::named('x'))];

        yield 'a directed edge read forwards' => ['-[e:x]->', new EdgePattern(EdgeDirection::Along, 'e', LabelPattern::named('x'))];

        yield 'a directed edge read either way' => ['<-[e:x]->', new EdgePattern(EdgeDirection::Either, 'e', LabelPattern::named('x'))];

        yield 'any edge either way' => ['-[e:x]-', new EdgePattern(EdgeDirection::Either, 'e', LabelPattern::named('x'))];

        yield 'an undirected edge' => ['~[e:x]~', new EdgePattern(EdgeDirection::Undirected, 'e', LabelPattern::named('x'))];

        yield 'an undirected edge or a directed one read backwards' => ['<~[e:x]~', new EdgePattern(EdgeDirection::Against, 'e', LabelPattern::named('x'))];

        yield 'an undirected edge or a directed one read forwards' => ['~[e:x]~>', new EdgePattern(EdgeDirection::Along, 'e', LabelPattern::named('x'))];

        yield 'the abbreviation read backwards' => ['<-', new EdgePattern(EdgeDirection::Against)];

        yield 'the abbreviation read forwards' => ['->', new EdgePattern(EdgeDirection::Along)];

        yield 'the abbreviation read either way' => ['<->', new EdgePattern(EdgeDirection::Either)];

        yield 'the abbreviation for any edge' => ['-', new EdgePattern(EdgeDirection::Either)];

        yield 'the abbreviation for an undirected edge' => ['~', new EdgePattern(EdgeDirection::Undirected)];

        yield 'the undirected abbreviation read backwards' => ['<~', new EdgePattern(EdgeDirection::Against)];

        yield 'the undirected abbreviation read forwards' => ['~>', new EdgePattern(EdgeDirection::Along)];

        yield 'a label after IS' => ['-[e IS x]->', new EdgePattern(EdgeDirection::Along, 'e', LabelPattern::named('x'))];

        yield 'a property' => [
            '-[e {line: 1}]->',
            new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(['line' => new LiteralExpression(new IntegerDatum(1))])),
        ];

        yield 'a bounded repetition' => ['-[e]->{1,3}', new EdgePattern(EdgeDirection::Along, 'e', null, new ElementFilter(), new Quantifier(1, 3))];
    }

    /**
     * @throws GqlException
     */
    #[DataProvider('providerArrowsGqlDoesNotDraw')]
    public function testParseEdgeRefusesAnArrowGqlDoesNotDraw(string $written, string $message): void
    {
        $tokens = TokenReader::of($written);

        $this->expectException(GqlException::class);
        $this->expectExceptionMessage($message);

        (new PatternParser($tokens, new ExpressionParser($tokens)))->parseEdge();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerArrowsGqlDoesNotDraw(): iterable
    {
        yield 'both a leading <~ and a trailing ~>' => ['<~[e]~>', 'expected an edge pattern, and GQL writes none that is both <~ and ~>'];

        yield 'the abbreviation of that' => ['<~>', 'expected an edge pattern, and GQL writes none that is both <~ and ~>'];

        yield 'a minus that closes a tilde' => ['~[e]-', 'expected "~" at line 1, column 5 (found "-")'];

        yield 'a tilde that closes a minus' => ['-[e]~', 'expected "-" at line 1, column 5 (found "~")'];

        yield 'brackets with nothing before them' => ['[e]->', 'expected "-" at line 1, column 1 (found "[")'];

        yield 'brackets that are never closed' => ['-[e', 'expected "]" at line 1, column 4 (found the end of the query)'];

        yield 'an unbounded repetition in a walk' => ['-[e]->*', 'write TRAIL, SIMPLE or ACYCLIC at line 1, column 7 (found "*")'];
    }

    #[DataProvider('providerArrowsAndWhichWayTheyCross')]
    public function testDirectionWorksOutWhichEdgesAnArrowCrosses(bool $backwards, bool $undirected, bool $forwards, EdgeDirection $expected): void
    {
        self::assertSame($expected, PatternParser::direction($backwards, $undirected, $forwards));
    }

    /**
     * @return iterable<string, array{bool, bool, bool, EdgeDirection}>
     */
    public static function providerArrowsAndWhichWayTheyCross(): iterable
    {
        yield '<-[ ]-' => [true, false, false, EdgeDirection::Against];

        yield '-[ ]->' => [false, false, true, EdgeDirection::Along];

        yield '<-[ ]->' => [true, false, true, EdgeDirection::Either];

        yield '-[ ]-' => [false, false, false, EdgeDirection::Either];

        yield '~[ ]~' => [false, true, false, EdgeDirection::Undirected];

        yield '<~[ ]~' => [true, true, false, EdgeDirection::Against];

        yield '~[ ]~>' => [false, true, true, EdgeDirection::Along];
    }
}
