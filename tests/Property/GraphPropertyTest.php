<?php

declare(strict_types=1);

namespace Tests\Property;

use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Edge\Inverse\UsedByEdge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use Eris\Generator\SequenceGenerator;
use Eris\Generator\TupleGenerator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * The graph model, checked over arbitrary sequences of relations rather than chosen ones.
 *
 * Each property adds a drawn list of relations to an empty graph and checks one of
 * the graph's own promises. Relations are drawn between four names, which is what
 * makes a list of ten relations hold cycles, repeats and shared endpoints rather than
 * ten unrelated pairs; Eris shrinks a failing list to the shortest one that still
 * breaks the promise.
 *
 * @group pbt
 *
 * @internal
 */
#[CoversClass(Graph::class)]
#[Group('pbt')]
#[Large]
final class GraphPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Checks that every relation the graph records is readable from both of its ends.
     */
    public function testEveryRelationIsReadableFromBothOfItsEnds(): void
    {
        $this->limitTo(200)
            ->forAll(new SequenceGenerator(new TupleGenerator([
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses))),
            ])))
            ->then(static function (array $relations): void {
                $graph = new Graph();
                foreach ($relations as $relation) {
                    self::assertIsArray($relation);
                    [$from, $to, $kind] = $relation;
                    self::assertIsString($from);
                    self::assertIsString($to);
                    self::assertInstanceOf(EdgeKind::class, $kind);
                    $graph->addEdge(new class (ClassNodeId::of($from), ClassNodeId::of($to), $kind) implements Edge {
                        public function __construct(
                            private readonly ClassNodeId $source,
                            private readonly ClassNodeId $target,
                            private readonly EdgeKind $drawn,
                        ) {}

                        public function from(): ClassNodeId
                        {
                            return $this->source;
                        }

                        public function to(): ClassNodeId
                        {
                            return $this->target;
                        }

                        public function kind(): EdgeKind
                        {
                            return $this->drawn;
                        }

                        public function meta(): FileMeta
                        {
                            return new FileMeta('/project/src/A.php', 1, 1);
                        }

                        public function invert(): Edge
                        {
                            return new UsedByEdge($this);
                        }
                    });
                }

                foreach ($graph->nodes() as $node) {
                    foreach ($graph->edges($node->id()) as $edge) {
                        self::assertNotNull($graph->edge($edge->to(), $edge->from()), sprintf('%s -[%s]-> %s has no reverse reading', $edge->from()->toString(), $edge->kind()->value, $edge->to()->toString()));
                    }
                }
            })
        ;
    }

    /**
     * Checks that no relation points at a symbol the graph does not hold.
     */
    public function testNoRelationPointsAtASymbolTheGraphDoesNotHold(): void
    {
        $this->limitTo(200)
            ->forAll(new SequenceGenerator(new TupleGenerator([
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses))),
            ])))
            ->then(static function (array $relations): void {
                $graph = new Graph();
                foreach ($relations as $relation) {
                    self::assertIsArray($relation);
                    [$from, $to, $kind] = $relation;
                    self::assertIsString($from);
                    self::assertIsString($to);
                    self::assertInstanceOf(EdgeKind::class, $kind);
                    $graph->addEdge(new class (ClassNodeId::of($from), ClassNodeId::of($to), $kind) implements Edge {
                        public function __construct(
                            private readonly ClassNodeId $source,
                            private readonly ClassNodeId $target,
                            private readonly EdgeKind $drawn,
                        ) {}

                        public function from(): ClassNodeId
                        {
                            return $this->source;
                        }

                        public function to(): ClassNodeId
                        {
                            return $this->target;
                        }

                        public function kind(): EdgeKind
                        {
                            return $this->drawn;
                        }

                        public function meta(): FileMeta
                        {
                            return new FileMeta('/project/src/A.php', 1, 1);
                        }

                        public function invert(): Edge
                        {
                            return new UsedByEdge($this);
                        }
                    });
                }

                foreach ($graph->nodes() as $node) {
                    foreach ($graph->edges($node->id()) as $edge) {
                        self::assertNotNull($graph->node($edge->from()), sprintf('%s is not in the graph', $edge->from()->toString()));
                        self::assertNotNull($graph->node($edge->to()), sprintf('%s is not in the graph', $edge->to()->toString()));
                    }
                }
            })
        ;
    }

    /**
     * Checks that an identifier never names more than one symbol, and that the same relation is recorded once.
     */
    public function testNeitherASymbolNorARelationIsRecordedTwice(): void
    {
        $this->limitTo(200)
            ->forAll(new SequenceGenerator(new TupleGenerator([
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses))),
            ])))
            ->then(static function (array $relations): void {
                $graph = new Graph();
                foreach ($relations as $relation) {
                    self::assertIsArray($relation);
                    [$from, $to, $kind] = $relation;
                    self::assertIsString($from);
                    self::assertIsString($to);
                    self::assertInstanceOf(EdgeKind::class, $kind);
                    $graph->addEdge(new class (ClassNodeId::of($from), ClassNodeId::of($to), $kind) implements Edge {
                        public function __construct(
                            private readonly ClassNodeId $source,
                            private readonly ClassNodeId $target,
                            private readonly EdgeKind $drawn,
                        ) {}

                        public function from(): ClassNodeId
                        {
                            return $this->source;
                        }

                        public function to(): ClassNodeId
                        {
                            return $this->target;
                        }

                        public function kind(): EdgeKind
                        {
                            return $this->drawn;
                        }

                        public function meta(): FileMeta
                        {
                            return new FileMeta('/project/src/A.php', 1, 1);
                        }

                        public function invert(): Edge
                        {
                            return new UsedByEdge($this);
                        }
                    });
                }

                $names = array_map(static fn (Node $node): string => $node->id()->toString(), $graph->nodes());
                self::assertSame($names, array_values(array_unique($names)));

                foreach ($graph->nodes() as $node) {
                    $spelled = array_map(static fn (Edge $edge): string => \App\Analyzer\Graph\EdgeIdentity::of($edge), $graph->edges($node->id()));
                    self::assertSame($spelled, array_values(array_unique($spelled)));
                }
            })
        ;
    }

    /**
     * Checks that inverting any recorded relation twice yields the relation it started from.
     */
    public function testInvertingAnyRelationTwiceYieldsTheRelationItStartedFrom(): void
    {
        $this->limitTo(200)
            ->forAll(new SequenceGenerator(new TupleGenerator([
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                Generators::elements(array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses))),
            ])))
            ->then(static function (array $relations): void {
                $graph = new Graph();
                foreach ($relations as $relation) {
                    self::assertIsArray($relation);
                    [$from, $to, $kind] = $relation;
                    self::assertIsString($from);
                    self::assertIsString($to);
                    self::assertInstanceOf(EdgeKind::class, $kind);
                    $graph->addEdge(new class (ClassNodeId::of($from), ClassNodeId::of($to), $kind) implements Edge {
                        public function __construct(
                            private readonly ClassNodeId $source,
                            private readonly ClassNodeId $target,
                            private readonly EdgeKind $drawn,
                        ) {}

                        public function from(): ClassNodeId
                        {
                            return $this->source;
                        }

                        public function to(): ClassNodeId
                        {
                            return $this->target;
                        }

                        public function kind(): EdgeKind
                        {
                            return $this->drawn;
                        }

                        public function meta(): FileMeta
                        {
                            return new FileMeta('/project/src/A.php', 1, 1);
                        }

                        public function invert(): Edge
                        {
                            return new UsedByEdge($this);
                        }
                    });
                }

                foreach ($graph->nodes() as $node) {
                    foreach ($graph->edges($node->id()) as $edge) {
                        self::assertSame($edge->kind(), $edge->invert()->invert()->kind());
                        self::assertSame($edge->from()->toString(), $edge->invert()->invert()->from()->toString());
                        self::assertSame($edge->to()->toString(), $edge->invert()->invert()->to()->toString());
                    }
                }
            })
        ;
    }

    /**
     * Checks that merging two graphs keeps every symbol and every relation of both.
     */
    public function testMergeKeepsEverySymbolAndRelationOfBothGraphs(): void
    {
        $this->limitTo(100)
            ->forAll(
                new SequenceGenerator(new TupleGenerator([
                    Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                    Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                    Generators::elements(array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses))),
                ])),
                new SequenceGenerator(new TupleGenerator([
                    Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                    Generators::elements(['App\A', 'App\B', 'App\C', 'App\D']),
                    Generators::elements(array_values(array_filter(EdgeKind::cases(), static fn (EdgeKind $kind): bool => $kind->direction() === Direction::Uses))),
                ])),
            )
            ->then(static function (array $left, array $right): void {
                $first = new Graph();
                $second = new Graph();
                foreach ([[$first, $left], [$second, $right]] as [$graph, $relations]) {
                    foreach ($relations as $relation) {
                        self::assertIsArray($relation);
                        [$from, $to, $kind] = $relation;
                        self::assertIsString($from);
                        self::assertIsString($to);
                        self::assertInstanceOf(EdgeKind::class, $kind);
                        $graph->addEdge(new class (ClassNodeId::of($from), ClassNodeId::of($to), $kind) implements Edge {
                            public function __construct(
                                private readonly ClassNodeId $source,
                                private readonly ClassNodeId $target,
                                private readonly EdgeKind $drawn,
                            ) {}

                            public function from(): ClassNodeId
                            {
                                return $this->source;
                            }

                            public function to(): ClassNodeId
                            {
                                return $this->target;
                            }

                            public function kind(): EdgeKind
                            {
                                return $this->drawn;
                            }

                            public function meta(): FileMeta
                            {
                                return new FileMeta('/project/src/A.php', 1, 1);
                            }

                            public function invert(): Edge
                            {
                                return new UsedByEdge($this);
                            }
                        });
                    }
                }

                $merged = $first->merge($second);
                $spell = static fn (Edge $edge): string => $edge->from()->toString().' -['.$edge->kind()->value.']-> '.$edge->to()->toString();
                $mergedRelations = array_map($spell, $merged->forwardEdges());

                foreach (array_merge($first->nodes(), $second->nodes()) as $node) {
                    self::assertNotNull($merged->node($node->id()), sprintf('%s was lost in the merge', $node->id()->toString()));
                }
                foreach (array_merge($first->forwardEdges(), $second->forwardEdges()) as $edge) {
                    self::assertContains($spell($edge), $mergedRelations);
                }
                self::assertSame($mergedRelations, array_values(array_unique($mergedRelations)));
            })
        ;
    }
}
