<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\ExperimentAnalyzer\Flow\State;
use App\Analyzer\ExperimentAnalyzer\Resolution\ScalarOrigins;
use App\Analyzer\Graph\Direction;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class ScalarOriginsTest extends TestCase
{
    public function testParameterUsesOnlyRuntimeScalarTypes(): void
    {
        self::assertTrue(ScalarOrigins::parameter(new Node\NullableType(new Node\Identifier('int'))));
        self::assertTrue(ScalarOrigins::parameter(new Node\UnionType([new Node\Identifier('string'), new Node\Identifier('bool')])));
        self::assertFalse(ScalarOrigins::parameter(new Node\UnionType([new Node\Identifier('string'), new Node\Name('Stringable')])));
        self::assertFalse(ScalarOrigins::parameter(new Node\Identifier('array')));
        self::assertFalse(ScalarOrigins::parameter(null));
        self::assertTrue(ScalarOrigins::parameter(new Node\Identifier('INT')));
    }

    public function testExpressionRequiresEveryReachingDefinitionToBeScalar(): void
    {
        $graph = new DependencyGraph('f', '', '');
        $graph->scalars = ['scalar' => true, 'object' => false];
        self::assertFalse(ScalarOrigins::expression(new Node\Expr\Variable('a'), new State(['$a' => ['scalar' => true, 'object' => true]]), $graph));
        self::assertTrue(ScalarOrigins::expression(new Node\Expr\Variable('a'), new State(['$a' => ['scalar' => true]]), $graph));
    }

    public function testLiteralDoesNotAssumeAnUnknownConstantIsScalar(): void
    {
        self::assertFalse(ScalarOrigins::literal(new Node\Expr\ConstFetch(new Node\Name('CUSTOM'))));
        self::assertTrue(ScalarOrigins::literal(new Node\Expr\ConstFetch(new Node\Name('null'))));
        self::assertTrue(ScalarOrigins::literal(new Node\Scalar\String_('value')));
        self::assertTrue(ScalarOrigins::literal(new Node\Expr\ConstFetch(new Node\Name('TRUE'))));
    }

    public function testInputsDoesNotCertifyAnEmptyOrPartiallyKnownSet(): void
    {
        $graph = new DependencyGraph('f', '', '');
        $graph->scalars = ['known' => true];
        self::assertFalse(ScalarOrigins::inputs([], $graph));
        self::assertFalse(ScalarOrigins::inputs(['known', 'unknown'], $graph));
        self::assertTrue(ScalarOrigins::inputs(['known'], $graph));
    }

    public function testEffectsRequiresScalarProofForCompoundConversion(): void
    {
        self::assertTrue(ScalarOrigins::effects(new Node\Expr\AssignOp\Concat(new Node\Expr\Variable('a'), new Node\Scalar\String_('')), new State(), new DependencyGraph('f', '', '')));
        self::assertTrue(ScalarOrigins::effects(new Node\Expr\BitwiseNot(new Node\Expr\Variable('a')), new State(), new DependencyGraph('f', '', '')));
        self::assertTrue(ScalarOrigins::expression(new Node\Expr\BitwiseNot(new Node\Scalar\Int_(1)), new State(), new DependencyGraph('f', '', '')));
    }

    #[DataProvider('providerImplicitEffects')]
    public function testEffectsNeverCertifiesImplicitExecution(string $body, string $code): void
    {
        $source = "<?php function f(\$object, \$other) {\n".$body."\nreturn \$other;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Node\Stmt\Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(3, 'other'), Direction::Uses, null);
        self::assertFalse($slice->analysis->complete);
        self::assertContains($code, array_column($slice->analysis->issues, 'code'));
        self::assertContains(2, array_column($slice->nodes, 'line'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerImplicitEffects(): iterable
    {
        yield 'string conversion' => ['$text = $object . "";', 'OPERAND_TYPES'];

        yield 'echo conversion' => ['echo $object;', 'OUTPUT_CONVERSION'];

        yield 'compound conversion' => ['$object .= "";', 'OPERAND_TYPES'];

        yield 'loose comparison' => ['$same = $object == "value";', 'OPERAND_TYPES'];

        yield 'object destruction' => ['$object = null;', 'VALUE_LIFETIME'];

        yield 'array destruction' => ['$copy = $object; $copy = null;', 'VALUE_LIFETIME'];
    }

    public function testEffectsKeepsTypedArithmeticAndScalarOverwritesClosed(): void
    {
        $source = "<?php function f(int \$left, int \$right) {\n\$result = \$left + \$right;\n\$left = 0;\nreturn \$result;\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Node\Stmt\Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(4, 'result'), Direction::Uses, null);
        self::assertTrue($slice->analysis->complete);
        self::assertSame('input', $slice->analysis->status);
        self::assertSame([], $slice->analysis->issues);
        self::assertNotContains(3, array_column($slice->nodes, 'line'));
    }

    public function testParameterKeepsAVariadicScalarDeclarationAnArray(): void
    {
        $source = '<?php function f(string ...$values) { echo $values; }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Node\Stmt\Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame(['OUTPUT_CONVERSION'], array_column($graph->issues, 'code'));
    }

    public function testParameterDoesNotTreatAPhp56ClassNamedIntAsAScalar(): void
    {
        $source = '<?php function f(int $value) { $value = null; }';
        $parsed = (new ParserFactory())->createForVersion(\PhpParser\PhpVersion::fromString('5.6'))->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Node\Stmt\Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        self::assertSame(['VALUE_LIFETIME'], array_column($graph->issues, 'code'));
    }
}
