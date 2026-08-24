<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Config\Config;
use App\Reporter\ReporterFactory;
use App\Reporter\TreeReporter\TreeReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixture\Graph\SampleGraph;

/**
 * @internal
 */
#[CoversClass(ReporterFactory::class)]
#[UsesClass(\App\Analyzer\Graph\AuthoredEdge::class)]
#[UsesClass(\App\Analyzer\Graph\EdgeKind::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclarationMethodEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\DeclaredInEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\MethodCallEdge::class)]
#[UsesClass(\App\Analyzer\Graph\Edge\UsedByEdge::class)]
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\Graph::class)]
#[UsesClass(ClassNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\ClassNode::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(Config::class)]
#[UsesClass(\App\Config\DebugAnalyzerConfig::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstTraversal::class)]
#[UsesClass(\App\Reporter\Traversal\DepthFirstWalk::class)]
#[UsesClass(\App\Reporter\TreeReporter\LineRenderer::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeCursor::class)]
#[UsesClass(TreeReporter::class)]
#[UsesClass(\App\Reporter\TreeReporter\TreeReporterOptions::class)]
#[Small]
final class ReporterFactoryTest extends TestCase
{
    public function testCreateBuildsTheTreeReporterTheProjectShipsWith(): void
    {
        self::assertInstanceOf(TreeReporter::class, (new ReporterFactory())->create(new Config('.', Direction::Uses)));
    }

    #[DataProvider('providerBothDirections')]
    public function testCreateGivesTheReporterTheDirectionTheConfigurationAsksFor(Direction $direction, string $expectedSecondLine): void
    {
        $output = new BufferedOutput();
        (new ReporterFactory())
            ->create(new Config('.', $direction))
            ->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringContainsString($expectedSecondLine, $output->fetch());
    }

    /**
     * @return iterable<string, array{Direction, string}>
     */
    public static function providerBothDirections(): iterable
    {
        yield 'away from the subject' => [Direction::Uses, 'App\Domain\Invoice::total'];

        yield 'towards the subject' => [Direction::UsedBy, 'App\Domain\Invoice'];
    }

    public function testCreateGivesTheReporterTheLevelBoundTheConfigurationAsksFor(): void
    {
        $output = new BufferedOutput();
        (new ReporterFactory())
            ->create(new Config('.', Direction::Uses, level: 1))
            ->report(SampleGraph::invoice(), ClassNodeId::of('App\Domain\Invoice'), $output)
        ;

        self::assertStringNotContainsString('App\Domain\Money::add', $output->fetch());
    }

    public function testCreateBuildsAFreshReporterForEachConfiguration(): void
    {
        $factory = new ReporterFactory();

        self::assertNotSame(
            $factory->create(new Config('.', Direction::Uses)),
            $factory->create(new Config('.', Direction::Uses)),
        );
    }
}
