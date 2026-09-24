<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Properties;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Selection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\ExperimentAnalyzer\SourceIndex;
use App\Analyzer\Graph\Direction;
use PhpParser\Node\Stmt\Class_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Analyzer\SourceParser::class)]
final class PropertiesTest extends TestCase
{
    public function testInspectFindsACaseInsensitiveClassAndCaseSensitiveProperty(): void
    {
        $file = dirname(__DIR__, 4).'/Fixture/Experimental/Properties.php';
        $graph = (new Inspection())->inspect(SourceIndex::of([$file], dirname($file)), '\tests\fixture\experimental\properties::$sources');
        self::assertSame('Tests\Fixture\Experimental\Properties::$sources', $graph->target);
        self::assertSame($file, $graph->file);
        $roots = (new Selection())->roots($graph, null, 'sources', null, Direction::Uses);
        self::assertCount(1, $roots);
        self::assertSame('property-access', $graph->nodes[$roots[0]]->kind);
        self::assertSame(15, $graph->nodes[$roots[0]]->line);
        $reverse = (new Selection())->roots($graph, null, 'sources', null, Direction::UsedBy);
        self::assertSame('property-declaration', $graph->nodes[$reverse[0]]->kind);
        self::assertSame(9, $graph->nodes[$reverse[0]]->line);
        $slice = Slice::of($graph, $roots, Direction::Uses, null);
        self::assertContains('truthy', array_column($slice->edges, 'branch'));
        self::assertContains('PROPERTY_LIFECYCLE', array_column($slice->analysis->issues, 'code'));
        self::assertFalse($slice->analysis->complete);
        self::assertSame(2, $slice->provenance['schemaVersion']);
        self::assertSame(hash_file('sha256', $file), $slice->provenance['sourceSha256']);
        $this->expectException(InspectionException::class);
        $graph->select(18, 'sources');
    }

    public function testAnalyzeRejectsAnUndeclaredPropertyInsteadOfMatchingALocal(): void
    {
        $source = '<?php class C { function f($sources) { return $sources; } }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Class_::class, $parsed[0]);
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Declared property not found');
        (new Properties())->analyze($parsed[0], 'sources', new DependencyGraph('C::$sources', '/f.php', $source));
    }

    public function testDeclarationRecognizesPromotedStorage(): void
    {
        $source = '<?php class C { function __construct(public int $x, int $local) {} }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Class_::class, $parsed[0]);
        $properties = new Properties();
        $declared = $properties->declaration($parsed[0], 'x');
        self::assertInstanceOf(\PhpParser\Node\Expr\Variable::class, $declared);
        self::assertSame('x', $declared->name);
        self::assertNull($properties->declaration($parsed[0], 'local'));
    }

    public function testSitesExcludeNestedReceiverScopesAndRetainDirectAccesses(): void
    {
        $source = '<?php class C { public $x; public $y; function f() { $this->x; self::$x; C::$x; $other->x; $this->y; $this->$name; return new class { function f() { return $this->x; } }; } }';
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Class_::class, $parsed[0]);
        $graph = (new Properties())->analyze($parsed[0], 'x', new DependencyGraph('C::$x', '/f.php', $source));
        self::assertSame(['$this->x', 'self::$x', 'C::$x'], array_column(array_filter($graph->nodes, static fn ($node) => $node->kind === 'property-access'), 'text'));
        self::assertNotNull($graph->inventory);
        self::assertSame(count((new \PhpParser\NodeFinder())->find([$parsed[0]], static fn () => true)), count($graph->inventory->sites));
    }

    #[DataProvider('providerReceivers')]
    public function testMatchesRequiresAWrittenMemberAndKnownReceiver(string $expression, bool $expected): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php '.$expression.';');
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        self::assertTrue($parsed[0]->expr instanceof \PhpParser\Node\Expr\PropertyFetch || $parsed[0]->expr instanceof \PhpParser\Node\Expr\NullsafePropertyFetch || $parsed[0]->expr instanceof \PhpParser\Node\Expr\StaticPropertyFetch);
        self::assertSame($expected, (new Properties())->matches($parsed[0]->expr, 'x', 'C::$x'));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerReceivers(): iterable
    {
        yield ['$this->x', true];

        yield ['$this?->x', true];

        yield ['self::$x', true];

        yield ['C::$x', true];

        yield ['Other::$x', false];

        yield ['$class::$x', false];

        yield ['static::$x', false];

        yield ['$other->x', false];

        yield ['$this->y', false];

        yield ['$this->$x', false];
    }

    public function testInspectRejectsAnAbsentClass(): void
    {
        $this->expectException(InspectionException::class);
        $this->expectExceptionMessage('Class not found: Missing.');
        (new Properties())->inspect(SourceIndex::of([], ''), 'Missing::$x');
    }
}
